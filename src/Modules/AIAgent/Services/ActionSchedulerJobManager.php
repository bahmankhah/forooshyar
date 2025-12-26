<?php
/**
 * Action Scheduler Job Manager
 * 
 * مدیریت کارهای تحلیل با استفاده از Action Scheduler
 * این سیستم پایدار است و نیازی به باز بودن مرورگر ندارد
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appLogger;

class ActionSchedulerJobManager
{
    // Hook names for Action Scheduler
    const HOOK_PROCESS_ITEM = 'aiagent_process_single_item';
    const HOOK_COMPLETE_JOB = 'aiagent_complete_job';
    const HOOK_CLEANUP_JOB = 'aiagent_cleanup_job';
    
    // Job state option
    const OPTION_JOB_STATE = 'aiagent_as_job_state';
    
    // Status constants
    const STATUS_IDLE = 'idle';
    const STATUS_RUNNING = 'running';
    const STATUS_CANCELLING = 'cancelling';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    // Group name for Action Scheduler
    const AS_GROUP = 'aiagent_analysis';

    /** @var ProductAnalyzer */
    private $productAnalyzer;

    /** @var CustomerAnalyzer */
    private $customerAnalyzer;

    /** @var SubscriptionManager */
    private $subscription;

    /** @var SettingsManager */
    private $settings;

    /** @var DatabaseService */
    private $database;

    /** @var ActionExecutor|null */
    private $actionExecutor;

    /** @var RateLimitService|null */
    private $rateLimitService;

    /** @var NotificationService|null */
    private $notificationService;

    /**
     * @param ProductAnalyzer $productAnalyzer
     * @param CustomerAnalyzer $customerAnalyzer
     * @param SubscriptionManager $subscription
     * @param SettingsManager $settings
     * @param DatabaseService $database
     * @param ActionExecutor|null $actionExecutor
     * @param RateLimitService|null $rateLimitService
     * @param NotificationService|null $notificationService
     */
    public function __construct(
        ProductAnalyzer $productAnalyzer,
        CustomerAnalyzer $customerAnalyzer,
        SubscriptionManager $subscription,
        SettingsManager $settings,
        DatabaseService $database,
        $actionExecutor = null,
        $rateLimitService = null,
        $notificationService = null
    ) {
        $this->productAnalyzer = $productAnalyzer;
        $this->customerAnalyzer = $customerAnalyzer;
        $this->subscription = $subscription;
        $this->settings = $settings;
        $this->database = $database;
        $this->actionExecutor = $actionExecutor;
        $this->rateLimitService = $rateLimitService;
        $this->notificationService = $notificationService;
        
        // NOTE: Hooks are registered in AIAgentModule::registerActionSchedulerHooks()
        // This ensures they are available BEFORE Action Scheduler tries to execute them
    }

    /**
     * Check if Action Scheduler is available (provided by WooCommerce)
     *
     * WooCommerce bundles Action Scheduler, so if WooCommerce is active,
     * Action Scheduler functions should be available.
     *
     * @return bool
     */
    public function isActionSchedulerAvailable()
    {
        // Action Scheduler is bundled with WooCommerce
        // These functions are available after plugins_loaded hook
        return function_exists('as_schedule_single_action') && 
               function_exists('as_unschedule_all_actions') &&
               function_exists('as_get_scheduled_actions');
    }
    
    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    public function isWooCommerceActive()
    {
        return class_exists('WooCommerce');
    }

    /**
     * Start a new analysis job
     *
     * @param string $type 'all', 'products', or 'customers'
     * @return array
     */
    public function startJob($type = 'all')
    {
        // Check WooCommerce first
        if (!$this->isWooCommerceActive()) {
            return [
                'success' => false,
                'error' => __('ووکامرس فعال نیست. این ماژول به ووکامرس نیاز دارد.', 'forooshyar'),
            ];
        }

        if (!$this->isActionSchedulerAvailable()) {
            return [
                'success' => false,
                'error' => __('Action Scheduler در دسترس نیست. لطفاً ووکامرس را به‌روزرسانی کنید.', 'forooshyar'),
            ];
        }

        // Check if a job is already running
        $currentJob = $this->getJobState();
        if ($currentJob['status'] === self::STATUS_RUNNING) {
            return [
                'success' => false,
                'error' => __('یک تحلیل در حال اجرا است. لطفاً صبر کنید یا آن را لغو کنید.', 'forooshyar'),
            ];
        }

        // Check subscription
        if (!$this->subscription->isModuleEnabled()) {
            return [
                'success' => false,
                'error' => __('ماژول دستیار هوشمند فعال نیست', 'forooshyar'),
            ];
        }

        // Get settings
        $includeProducts = $this->settings->get('analysis_include_products', true);
        $includeCustomers = $this->settings->get('analysis_include_customers', true);

        // Get entities to analyze
        $products = [];
        $customers = [];

        if (($type === 'all' || $type === 'products') && $includeProducts) {
            $limit = $this->settings->get('analysis_product_limit', 50);
            $products = $this->productAnalyzer->getEntities($limit);
            appLogger("[AIAgent-AS] Found " . count($products) . " products to analyze");
        }

        if (($type === 'all' || $type === 'customers') && $includeCustomers) {
            $limit = $this->settings->get('analysis_customer_limit', 100);
            $customers = $this->customerAnalyzer->getEntities($limit);
            appLogger("[AIAgent-AS] Found " . count($customers) . " customers to analyze");
        }

        $totalItems = count($products) + count($customers);
        
        if ($totalItems === 0) {
            return [
                'success' => false,
                'error' => __('هیچ موردی برای تحلیل یافت نشد', 'forooshyar'),
            ];
        }

        // Create job state
        $jobId = uniqid('asjob_', true);
        $jobState = [
            'id' => $jobId,
            'status' => self::STATUS_RUNNING,
            'type' => $type,
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'products_total' => count($products),
            'products_processed' => 0,
            'products_success' => 0,
            'products_failed' => 0,
            'customers_total' => count($customers),
            'customers_processed' => 0,
            'customers_success' => 0,
            'customers_failed' => 0,
            'current_item' => null,
            'errors' => [],
            'actions_created' => 0,
        ];

        $this->saveJobState($jobState);

        // Clear any existing scheduled actions for this group
        as_unschedule_all_actions(self::HOOK_PROCESS_ITEM, [], self::AS_GROUP);
        as_unschedule_all_actions(self::HOOK_COMPLETE_JOB, [], self::AS_GROUP);

        // Schedule each item for processing
        $delay = 0;
        $delayIncrement = 2; // 2 seconds between each item to avoid overwhelming LLM

        foreach ($products as $product) {
            as_schedule_single_action(
                time() + $delay,
                self::HOOK_PROCESS_ITEM,
                [$jobId, 'product', $product['id']],
                self::AS_GROUP
            );
            $delay += $delayIncrement;
        }

        foreach ($customers as $customer) {
            as_schedule_single_action(
                time() + $delay,
                self::HOOK_PROCESS_ITEM,
                [$jobId, 'customer', $customer['id']],
                self::AS_GROUP
            );
            $delay += $delayIncrement;
        }

        // Schedule job completion check (after all items should be processed)
        as_schedule_single_action(
            time() + $delay + 60,
            self::HOOK_COMPLETE_JOB,
            [$jobId],
            self::AS_GROUP
        );

        appLogger("[AIAgent-AS] Job started: {$jobId} - Products: " . count($products) . ", Customers: " . count($customers));

        return [
            'success' => true,
            'job_id' => $jobId,
            'message' => sprintf(
                __('تحلیل شروع شد. %d مورد در صف تحلیل قرار گرفت.', 'forooshyar'),
                $totalItems
            ),
            'total_items' => $totalItems,
        ];
    }

    /**
     * Process a single item (called by Action Scheduler)
     *
     * @param string $jobId
     * @param string $entityType 'product' or 'customer'
     * @param int $entityId
     * @return void
     */
    public function processItem($jobId, $entityType, $entityId)
    {
        $jobState = $this->getJobState();

        // Verify job is still running
        if ($jobState['id'] !== $jobId || $jobState['status'] !== self::STATUS_RUNNING) {
            appLogger("[AIAgent-AS] Skipping item - job not running: {$jobId}");
            return;
        }

        // Check for cancellation
        if ($jobState['status'] === self::STATUS_CANCELLING) {
            appLogger("[AIAgent-AS] Job cancelled, skipping item");
            return;
        }

        // Update current item
        $jobState['current_item'] = ['type' => $entityType, 'id' => $entityId];
        $jobState['updated_at'] = current_time('mysql');
        $this->saveJobState($jobState);

        appLogger("[AIAgent-AS] Processing {$entityType} #{$entityId}");

        // Check rate limits before making LLM call
        if ($this->rateLimitService) {
            $rateLimitCheck = $this->rateLimitService->checkAndIncrement();
            
            if (!$rateLimitCheck['allowed']) {
                // Rate limit exceeded - reschedule this item for later
                $rescheduleTime = time() + $rateLimitCheck['reschedule_delay'];
                
                appLogger("[AIAgent-AS] Rate limit exceeded, rescheduling {$entityType} #{$entityId} for " . date('Y-m-d H:i:s', $rescheduleTime));
                
                as_schedule_single_action(
                    $rescheduleTime,
                    self::HOOK_PROCESS_ITEM,
                    [$jobId, $entityType, $entityId],
                    self::AS_GROUP
                );
                
                return;
            }
        }

        try {
            if ($entityType === 'product') {
                $result = $this->productAnalyzer->analyzeEntity($entityId);
                $jobState['products_processed']++;
                
                if ($result['success']) {
                    $jobState['products_success']++;
                    if (!empty($result['suggestions'])) {
                        $actionsCreated = $this->createActionsFromSuggestions($result);
                        $jobState['actions_created'] += $actionsCreated;
                    }
                } else {
                    $jobState['products_failed']++;
                    $jobState['errors'][] = [
                        'type' => 'product',
                        'id' => $entityId,
                        'error' => $result['error'] ?? __('خطای ناشناخته', 'forooshyar'),
                    ];
                }
            } else {
                $result = $this->customerAnalyzer->analyzeEntity($entityId);
                $jobState['customers_processed']++;
                
                if ($result['success']) {
                    $jobState['customers_success']++;
                    if (!empty($result['suggestions'])) {
                        $actionsCreated = $this->createActionsFromSuggestions($result);
                        $jobState['actions_created'] += $actionsCreated;
                    }
                } else {
                    $jobState['customers_failed']++;
                    $jobState['errors'][] = [
                        'type' => 'customer',
                        'id' => $entityId,
                        'error' => $result['error'] ?? __('خطای ناشناخته', 'forooshyar'),
                    ];
                }
            }
        } catch (\Exception $e) {
            if ($entityType === 'product') {
                $jobState['products_processed']++;
                $jobState['products_failed']++;
            } else {
                $jobState['customers_processed']++;
                $jobState['customers_failed']++;
            }
            $jobState['errors'][] = [
                'type' => $entityType,
                'id' => $entityId,
                'error' => $e->getMessage(),
            ];
            appLogger("[AIAgent-AS] Exception processing {$entityType} #{$entityId}: " . $e->getMessage());
            
            // Notify about error
            if ($this->notificationService) {
                $this->notificationService->notifyError($e->getMessage(), [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'job_id' => $jobId,
                ]);
            }
        }

        $jobState['current_item'] = null;
        $jobState['updated_at'] = current_time('mysql');
        $this->saveJobState($jobState);

        // Check if all items are processed
        $totalProcessed = $jobState['products_processed'] + $jobState['customers_processed'];
        $totalItems = $jobState['products_total'] + $jobState['customers_total'];

        if ($totalProcessed >= $totalItems) {
            // All items processed, complete the job
            $this->completeJob($jobId);
        }
    }

    /**
     * Complete the job
     *
     * @param string $jobId
     * @return void
     */
    public function completeJob($jobId)
    {
        $jobState = $this->getJobState();

        if ($jobState['id'] !== $jobId) {
            return;
        }

        if ($jobState['status'] === self::STATUS_COMPLETED) {
            return; // Already completed
        }

        $jobState['status'] = self::STATUS_COMPLETED;
        $jobState['completed_at'] = current_time('mysql');
        $jobState['current_item'] = null;

        // Increment usage
        $this->subscription->incrementUsage('analyses_per_day');

        // Auto-execute approved actions if enabled
        $actionsExecuted = 0;
        if ($this->settings->get('actions_auto_execute', false) && $this->actionExecutor) {
            $actionsExecuted = $this->executeApprovedActions();
            $jobState['actions_executed'] = $actionsExecuted;
        }

        $this->saveJobState($jobState);

        // Save analysis run record
        $this->database->saveAnalysisRun([
            'type' => $jobState['type'],
            'success' => true,
            'products_analyzed' => $jobState['products_success'],
            'customers_analyzed' => $jobState['customers_success'],
            'actions_created' => $jobState['actions_created'],
            'actions_executed' => $actionsExecuted,
            'duration_ms' => 0,
        ]);

        // Clear scheduled actions
        as_unschedule_all_actions(self::HOOK_PROCESS_ITEM, [], self::AS_GROUP);
        as_unschedule_all_actions(self::HOOK_COMPLETE_JOB, [], self::AS_GROUP);

        appLogger("[AIAgent-AS] Job completed: {$jobId} - Products: {$jobState['products_success']}/{$jobState['products_total']}, Customers: {$jobState['customers_success']}/{$jobState['customers_total']}");
    }

    /**
     * Cancel the current job
     *
     * @return array
     */
    public function cancelJob()
    {
        $jobState = $this->getJobState();

        if ($jobState['status'] !== self::STATUS_RUNNING) {
            return [
                'success' => false,
                'error' => __('هیچ تحلیلی در حال اجرا نیست', 'forooshyar'),
            ];
        }

        // Cancel all pending actions
        as_unschedule_all_actions(self::HOOK_PROCESS_ITEM, [], self::AS_GROUP);
        as_unschedule_all_actions(self::HOOK_COMPLETE_JOB, [], self::AS_GROUP);

        $jobState['status'] = self::STATUS_CANCELLED;
        $jobState['updated_at'] = current_time('mysql');
        $jobState['current_item'] = null;
        $this->saveJobState($jobState);

        appLogger("[AIAgent-AS] Job cancelled: {$jobState['id']}");

        return [
            'success' => true,
            'message' => __('تحلیل لغو شد', 'forooshyar'),
        ];
    }

    /**
     * Get job progress
     *
     * @return array
     */
    public function getJobProgress()
    {
        $state = $this->getJobState();

        if ($state['status'] === self::STATUS_IDLE) {
            return [
                'status' => self::STATUS_IDLE,
                'job_id' => null,
                'is_running' => false,
                'is_cancelling' => false,
                'progress' => 0,
                'percentage' => 0,
                'products_total' => 0,
                'products_analyzed' => 0,
                'products_processed' => 0,
                'products_failed' => 0,
                'customers_total' => 0,
                'customers_analyzed' => 0,
                'customers_processed' => 0,
                'customers_failed' => 0,
                'actions_created' => 0,
                'current_item' => null,
                'errors' => [],
                'started_at' => null,
                'updated_at' => null,
                'completed_at' => null,
                'pending_actions' => 0,
            ];
        }

        $totalItems = $state['products_total'] + $state['customers_total'];
        $processedItems = $state['products_processed'] + $state['customers_processed'];
        $percentage = $totalItems > 0 ? round(($processedItems / $totalItems) * 100) : 0;

        // Get pending Action Scheduler actions count
        $pendingActions = 0;
        if ($this->isActionSchedulerAvailable()) {
            $pendingActions = as_get_scheduled_actions([
                'hook' => self::HOOK_PROCESS_ITEM,
                'group' => self::AS_GROUP,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
            ], 'ids');
            $pendingActions = is_array($pendingActions) ? count($pendingActions) : 0;
        }

        // Format current item for display
        $currentItemText = null;
        if (!empty($state['current_item'])) {
            $type = $state['current_item']['type'] === 'product' ? 'محصول' : 'مشتری';
            $currentItemText = $type . ' #' . $state['current_item']['id'];
        }

        return [
            'status' => $state['status'],
            'job_id' => $state['id'] ?? null,
            'is_running' => $state['status'] === self::STATUS_RUNNING,
            'is_cancelling' => $state['status'] === self::STATUS_CANCELLING,
            'is_completed' => $state['status'] === self::STATUS_COMPLETED,
            'is_failed' => $state['status'] === self::STATUS_FAILED,
            'is_cancelled' => $state['status'] === self::STATUS_CANCELLED,
            'progress' => $percentage,
            'percentage' => $percentage,
            'products_total' => $state['products_total'] ?? 0,
            'products_analyzed' => $state['products_success'] ?? 0,
            'products_processed' => $state['products_processed'] ?? 0,
            'products_failed' => $state['products_failed'] ?? 0,
            'customers_total' => $state['customers_total'] ?? 0,
            'customers_analyzed' => $state['customers_success'] ?? 0,
            'customers_processed' => $state['customers_processed'] ?? 0,
            'customers_failed' => $state['customers_failed'] ?? 0,
            'actions_created' => $state['actions_created'] ?? 0,
            'current_item' => $currentItemText,
            'errors' => isset($state['errors']) ? array_slice($state['errors'], -5) : [],
            'started_at' => $state['started_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'completed_at' => $state['completed_at'] ?? null,
            'pending_actions' => $pendingActions,
        ];
    }

    /**
     * Get job state from options
     *
     * @return array
     */
    public function getJobState()
    {
        $state = get_option(self::OPTION_JOB_STATE, null);
        
        if (!$state) {
            return $this->getDefaultState();
        }

        return $state;
    }

    /**
     * Save job state to options
     *
     * @param array $state
     * @return void
     */
    private function saveJobState(array $state)
    {
        update_option(self::OPTION_JOB_STATE, $state, false);
    }

    /**
     * Get default state
     *
     * @return array
     */
    private function getDefaultState()
    {
        return [
            'id' => null,
            'status' => self::STATUS_IDLE,
            'type' => null,
            'started_at' => null,
            'updated_at' => null,
            'completed_at' => null,
            'products_total' => 0,
            'products_processed' => 0,
            'products_success' => 0,
            'products_failed' => 0,
            'customers_total' => 0,
            'customers_processed' => 0,
            'customers_success' => 0,
            'customers_failed' => 0,
            'current_item' => null,
            'errors' => [],
            'actions_created' => 0,
        ];
    }

    /**
     * Acknowledge completion and clear state
     *
     * @return void
     */
    public function acknowledgeCompletion()
    {
        $state = $this->getJobState();
        
        if (in_array($state['status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_FAILED], true)) {
            delete_option(self::OPTION_JOB_STATE);
            appLogger("[AIAgent-AS] Job state cleared after acknowledgement");
        }
    }

    /**
     * Reset job state
     *
     * @return void
     */
    public function resetJobState()
    {
        delete_option(self::OPTION_JOB_STATE);
        
        if ($this->isActionSchedulerAvailable()) {
            as_unschedule_all_actions(self::HOOK_PROCESS_ITEM, [], self::AS_GROUP);
            as_unschedule_all_actions(self::HOOK_COMPLETE_JOB, [], self::AS_GROUP);
        }
    }

    /**
     * Create actions from suggestions
     *
     * @param array $analysisResult
     * @return int
     */
    private function createActionsFromSuggestions(array $analysisResult)
    {
        if (empty($analysisResult['suggestions'])) {
            return 0;
        }

        $enabledActions = $this->settings->get('actions_enabled_types', []);
        $created = 0;

        foreach ($analysisResult['suggestions'] as $suggestion) {
            $actionType = $suggestion['type'] ?? '';

            if (!in_array($actionType, $enabledActions, true)) {
                continue;
            }

            $priority = (int) ($suggestion['priority'] ?? 50);

            $suggestionData = $suggestion['data'] ?? [];
            
            if (!empty($suggestion['reasoning'])) {
                $suggestionData['reasoning'] = $suggestion['reasoning'];
            }
            
            if (!empty($analysisResult['entity_id'])) {
                $suggestionData['entity_id'] = $analysisResult['entity_id'];
            }
            if (!empty($analysisResult['entity_type'])) {
                $suggestionData['entity_type'] = $analysisResult['entity_type'];
            }

            // All actions are created as 'pending' - no approval step needed
            // Users can execute any pending action directly
            $actionData = [
                'analysis_id' => $analysisResult['id'] ?? null,
                'action_type' => $actionType,
                'action_data' => $suggestionData,
                'status' => 'pending',
                'priority_score' => $priority,
                'requires_approval' => 0,
            ];

            $actionId = $this->database->saveAction($actionData);
            if ($actionId) {
                $created++;
                
                // Notify for high priority actions
                $priorityThreshold = (int) $this->settings->get('analysis_priority_threshold', 70);
                if ($priority >= $priorityThreshold && $this->notificationService) {
                    $actionData['id'] = $actionId;
                    $this->notificationService->notifyHighPriorityAction($actionData);
                }
            }
        }

        return $created;
    }

    /**
     * Execute pending actions with high priority (auto-execute)
     *
     * @return int
     */
    private function executeApprovedActions()
    {
        if (!$this->actionExecutor) {
            return 0;
        }

        $limit = (int) $this->settings->get('actions_max_per_run', 10);
        $priorityThreshold = (int) $this->settings->get('analysis_priority_threshold', 70);
        
        // Get pending actions (not just approved, since we removed approval step)
        $actions = $this->database->getActions([
            'status' => 'pending',
        ], $limit, 0);

        $executed = 0;

        foreach ($actions as $action) {
            if ($action['priority_score'] < $priorityThreshold) {
                continue;
            }

            try {
                $result = $this->actionExecutor->executeById($action['id']);
                if ($result['success']) {
                    $executed++;
                }
            } catch (\Exception $e) {
                appLogger("[AIAgent-AS] Error executing action #{$action['id']}: " . $e->getMessage());
            }
        }

        return $executed;
    }

    /**
     * Cleanup old job data
     *
     * @param string $jobId
     * @return void
     */
    public function cleanupJob($jobId)
    {
        // This can be used for any cleanup tasks after job completion
        appLogger("[AIAgent-AS] Cleanup for job: {$jobId}");
    }

    /**
     * Process next batch (compatibility method for existing code)
     * This is called by the old cron system - we redirect to Action Scheduler
     *
     * @return void
     */
    public function processNextBatch()
    {
        // If Action Scheduler is available, it handles processing
        // This method is kept for backward compatibility
        if ($this->isActionSchedulerAvailable()) {
            return;
        }
        
        // Fallback to old behavior if Action Scheduler is not available
        // (This shouldn't happen in normal circumstances)
        appLogger("[AIAgent-AS] Warning: processNextBatch called but Action Scheduler should handle this");
    }
}
