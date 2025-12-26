<?php
/**
 * Analysis Job Manager
 * 
 * Manages async analysis jobs with progress tracking and cancellation support.
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;
use function Forooshyar\WPLite\appLogger;

class AnalysisJobManager
{
    const OPTION_JOB_STATE = 'aiagent_analysis_job';
    const CRON_HOOK = 'aiagent_process_analysis_job';
    
    const STATUS_IDLE = 'idle';
    const STATUS_RUNNING = 'running';
    const STATUS_CANCELLING = 'cancelling';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

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

    /**
     * @param ProductAnalyzer $productAnalyzer
     * @param CustomerAnalyzer $customerAnalyzer
     * @param SubscriptionManager $subscription
     * @param SettingsManager $settings
     * @param DatabaseService $database
     * @param ActionExecutor|null $actionExecutor
     */
    public function __construct(
        ProductAnalyzer $productAnalyzer,
        CustomerAnalyzer $customerAnalyzer,
        SubscriptionManager $subscription,
        SettingsManager $settings,
        DatabaseService $database,
        $actionExecutor = null
    ) {
        $this->productAnalyzer = $productAnalyzer;
        $this->customerAnalyzer = $customerAnalyzer;
        $this->subscription = $subscription;
        $this->settings = $settings;
        $this->database = $database;
        $this->actionExecutor = $actionExecutor;
    }

    /**
     * Start a new analysis job
     *
     * @param string $type 'all', 'products', or 'customers'
     * @return array
     */
    public function startJob($type = 'all')
    {
        // Check if job is already running
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

        // Check analysis type settings - default to true if not set
        $includeProducts = $this->settings->get('analysis_include_products');
        $includeCustomers = $this->settings->get('analysis_include_customers');
        
        // Default to true if settings are not explicitly set
        if ($includeProducts === null || $includeProducts === '') {
            $includeProducts = true;
        } else {
            $includeProducts = (bool) $includeProducts;
        }
        
        if ($includeCustomers === null || $includeCustomers === '') {
            $includeCustomers = true;
        } else {
            $includeCustomers = (bool) $includeCustomers;
        }
        
        appLogger("[AIAgent] Analysis settings - includeProducts: " . ($includeProducts ? 'yes' : 'no') . ", includeCustomers: " . ($includeCustomers ? 'yes' : 'no'));

        // Get entities to analyze
        $products = [];
        $customers = [];

        if ($type === 'all' || $type === 'products') {
            if ($includeProducts) {
                $limit = $this->settings->get('analysis_product_limit', 50);
                if (!$limit || $limit <= 0) {
                    $limit = 50;
                }
                appLogger("[AIAgent] Fetching products with limit: {$limit}");
                $products = $this->productAnalyzer->getEntities($limit);
                appLogger("[AIAgent] Got " . count($products) . " products");
            }
        }

        if ($type === 'all' || $type === 'customers') {
            if ($includeCustomers) {
                $limit = $this->settings->get('analysis_customer_limit', 100);
                if (!$limit || $limit <= 0) {
                    $limit = 100;
                }
                appLogger("[AIAgent] Fetching customers with limit: {$limit}");
                $customers = $this->customerAnalyzer->getEntities($limit);
                appLogger("[AIAgent] Got " . count($customers) . " customers");
            }
        }

        $totalItems = count($products) + count($customers);
        
        appLogger("[AIAgent] Total items to analyze: {$totalItems} (products: " . count($products) . ", customers: " . count($customers) . ")");
        
        if ($totalItems === 0) {
            $reasons = [];
            if (!$includeProducts && !$includeCustomers) {
                $reasons[] = __('تحلیل محصولات و مشتریان در تنظیمات غیرفعال است', 'forooshyar');
            } else {
                if ($includeProducts && empty($products)) {
                    $reasons[] = __('هیچ محصول منتشر شده‌ای یافت نشد', 'forooshyar');
                }
                if ($includeCustomers && empty($customers)) {
                    $reasons[] = __('هیچ مشتری‌ای یافت نشد', 'forooshyar');
                }
            }
            
            $errorMsg = !empty($reasons) 
                ? implode('. ', $reasons) 
                : __('هیچ موردی برای تحلیل یافت نشد', 'forooshyar');
            
            return [
                'success' => false,
                'error' => $errorMsg,
            ];
        }

        // Create job state
        $jobId = uniqid('job_', true);
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
            'pending_products' => array_column($products, 'id'),
            'pending_customers' => array_column($customers, 'id'),
        ];

        $this->saveJobState($jobState);

        // Schedule immediate processing
        $this->scheduleProcessing();

        appLogger("[AIAgent] Job started: {$jobId} - Products: " . count($products) . ", Customers: " . count($customers));

        return [
            'success' => true,
            'job_id' => $jobId,
            'message' => sprintf(
                __('تحلیل شروع شد. %d مورد در صف تحلیل قرار گرفت.', 'forooshyar'),
                $totalItems
            ),
        ];
    }

    /**
     * Process the next batch of items
     *
     * @return void
     */
    public function processNextBatch()
    {
        $jobState = $this->getJobState();

        // Check if job should continue
        if ($jobState['status'] !== self::STATUS_RUNNING) {
            if ($jobState['status'] === self::STATUS_CANCELLING) {
                $jobState['status'] = self::STATUS_CANCELLED;
                $jobState['updated_at'] = current_time('mysql');
                $this->saveJobState($jobState);
                appLogger("[AIAgent] Job cancelled: {$jobState['id']}");
            }
            return;
        }

        $batchSize = 1; // Process one item at a time for better progress tracking
        $processed = 0;

        // Process products first
        while ($processed < $batchSize && !empty($jobState['pending_products'])) {
            $productId = array_shift($jobState['pending_products']);
            $jobState['current_item'] = ['type' => 'product', 'id' => $productId];
            $this->saveJobState($jobState);

            try {
                $result = $this->productAnalyzer->analyzeEntity($productId);
                $jobState['products_processed']++;
                
                if ($result['success']) {
                    $jobState['products_success']++;
                    if (!empty($result['suggestions'])) {
                        $jobState['actions_created'] += $this->createActionsFromSuggestions($result);
                    }
                } else {
                    $jobState['products_failed']++;
                    $jobState['errors'][] = [
                        'type' => 'product',
                        'id' => $productId,
                        'error' => $result['error'] ?? __('خطای ناشناخته', 'forooshyar'),
                    ];
                }
            } catch (\Exception $e) {
                $jobState['products_processed']++;
                $jobState['products_failed']++;
                $jobState['errors'][] = [
                    'type' => 'product',
                    'id' => $productId,
                    'error' => $e->getMessage(),
                ];
                appLogger("[AIAgent] Product {$productId} exception: " . $e->getMessage());
            }

            $processed++;
            
            // Check for cancellation
            $currentState = $this->getJobState();
            if ($currentState['status'] === self::STATUS_CANCELLING) {
                $jobState['status'] = self::STATUS_CANCELLED;
                $jobState['updated_at'] = current_time('mysql');
                $jobState['current_item'] = null;
                $this->saveJobState($jobState);
                appLogger("[AIAgent] Job cancelled during processing: {$jobState['id']}");
                return;
            }
        }

        // Process customers
        while ($processed < $batchSize && !empty($jobState['pending_customers'])) {
            $customerId = array_shift($jobState['pending_customers']);
            $jobState['current_item'] = ['type' => 'customer', 'id' => $customerId];
            $this->saveJobState($jobState);

            try {
                $result = $this->customerAnalyzer->analyzeEntity($customerId);
                $jobState['customers_processed']++;
                
                if ($result['success']) {
                    $jobState['customers_success']++;
                    if (!empty($result['suggestions'])) {
                        $jobState['actions_created'] += $this->createActionsFromSuggestions($result);
                    }
                } else {
                    $jobState['customers_failed']++;
                    $jobState['errors'][] = [
                        'type' => 'customer',
                        'id' => $customerId,
                        'error' => $result['error'] ?? __('خطای ناشناخته', 'forooshyar'),
                    ];
                }
            } catch (\Exception $e) {
                $jobState['customers_processed']++;
                $jobState['customers_failed']++;
                $jobState['errors'][] = [
                    'type' => 'customer',
                    'id' => $customerId,
                    'error' => $e->getMessage(),
                ];
            }

            $processed++;
            
            // Check for cancellation
            $currentState = $this->getJobState();
            if ($currentState['status'] === self::STATUS_CANCELLING) {
                $jobState['status'] = self::STATUS_CANCELLED;
                $jobState['updated_at'] = current_time('mysql');
                $jobState['current_item'] = null;
                $this->saveJobState($jobState);
                return;
            }
        }

        // Update job state
        $jobState['updated_at'] = current_time('mysql');
        $jobState['current_item'] = null;

        // Check if job is complete
        if (empty($jobState['pending_products']) && empty($jobState['pending_customers'])) {
            $jobState['status'] = self::STATUS_COMPLETED;
            $jobState['completed_at'] = current_time('mysql');
            
            // Increment usage
            $this->subscription->incrementUsage('analyses_per_day');
            
            // Auto-execute approved actions if enabled
            $actionsExecuted = 0;
            if ($this->settings->get('actions_auto_execute', false) && $this->actionExecutor) {
                $actionsExecuted = $this->executeApprovedActions();
                $jobState['actions_executed'] = $actionsExecuted;
                appLogger("[AIAgent] Auto-executed {$actionsExecuted} approved actions");
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
            
            appLogger("[AIAgent] Job completed: {$jobState['id']} - Products: {$jobState['products_success']}/{$jobState['products_total']}, Customers: {$jobState['customers_success']}/{$jobState['customers_total']}, Actions executed: {$actionsExecuted}");
        } else {
            $this->saveJobState($jobState);
            // Schedule next batch
            $this->scheduleProcessing();
        }
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

        $jobState['status'] = self::STATUS_CANCELLING;
        $jobState['updated_at'] = current_time('mysql');
        $this->saveJobState($jobState);

        appLogger("[AIAgent] Job cancellation requested: {$jobState['id']}");

        return [
            'success' => true,
            'message' => __('درخواست لغو تحلیل ارسال شد', 'forooshyar'),
        ];
    }

    /**
     * Get current job state
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
     * Get job progress for display
     *
     * @return array
     */
    public function getJobProgress()
    {
        $state = $this->getJobState();
        
        $totalItems = $state['products_total'] + $state['customers_total'];
        $processedItems = $state['products_processed'] + $state['customers_processed'];
        $percentage = $totalItems > 0 ? round(($processedItems / $totalItems) * 100) : 0;

        // Format current item for display
        $currentItemText = null;
        if ($state['current_item']) {
            $type = $state['current_item']['type'] === 'product' ? 'محصول' : 'مشتری';
            $currentItemText = $type . ' #' . $state['current_item']['id'];
        }

        return [
            'status' => $state['status'],
            'job_id' => $state['id'] ?? null,
            'is_running' => $state['status'] === self::STATUS_RUNNING,
            'is_cancelling' => $state['status'] === self::STATUS_CANCELLING,
            'progress' => $percentage,
            'percentage' => $percentage,
            'products_total' => $state['products_total'],
            'products_analyzed' => $state['products_success'],
            'products_processed' => $state['products_processed'],
            'products_failed' => $state['products_failed'],
            'customers_total' => $state['customers_total'],
            'customers_analyzed' => $state['customers_success'],
            'customers_processed' => $state['customers_processed'],
            'customers_failed' => $state['customers_failed'],
            'actions_created' => $state['actions_created'],
            'current_item' => $currentItemText,
            'errors' => \array_slice($state['errors'], -5), // Last 5 errors
            'started_at' => $state['started_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'completed_at' => $state['completed_at'] ?? null,
        ];
    }

    /**
     * Reset job state (for cleanup)
     *
     * @return void
     */
    public function resetJobState()
    {
        delete_option(self::OPTION_JOB_STATE);
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Save job state
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
            'pending_products' => [],
            'pending_customers' => [],
        ];
    }

    /**
     * Schedule processing via cron
     *
     * @return void
     */
    private function scheduleProcessing()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 1, self::CRON_HOOK);
        }
    }

    /**
     * Create actions from analysis suggestions
     *
     * @param array $analysisResult
     * @return int Number of actions created
     */
    private function createActionsFromSuggestions(array $analysisResult)
    {
        if (empty($analysisResult['suggestions'])) {
            appLogger("[AIAgent] No suggestions to create actions from");
            return 0;
        }

        $enabledActions = $this->settings->get('actions_enabled_types', []);
        $requireApproval = $this->settings->get('actions_require_approval', []);
        $created = 0;

        appLogger("[AIAgent] Creating actions from " . \count($analysisResult['suggestions']) . " suggestions");
        appLogger("[AIAgent] Enabled action types: " . implode(', ', $enabledActions));

        foreach ($analysisResult['suggestions'] as $suggestion) {
            $actionType = $suggestion['type'] ?? '';

            appLogger("[AIAgent] Processing suggestion type: {$actionType}");

            if (!\in_array($actionType, $enabledActions, true)) {
                appLogger("[AIAgent] Skipping action type '{$actionType}' - not in enabled types");
                continue;
            }

            $priority = (int) ($suggestion['priority'] ?? 50);
            $needsApproval = \in_array($actionType, $requireApproval, true);

            // Include reasoning and entity info in action_data for display and deduplication
            $suggestionData = $suggestion['data'] ?? [];
            
            // Copy reasoning from suggestion to action_data
            if (!empty($suggestion['reasoning'])) {
                $suggestionData['reasoning'] = $suggestion['reasoning'];
                appLogger("[AIAgent] Copying reasoning to action_data: " . substr($suggestion['reasoning'], 0, 100));
            } else {
                appLogger("[AIAgent] No reasoning found in suggestion for type: {$actionType}");
            }
            
            // Add entity info for deduplication
            if (!empty($analysisResult['entity_id'])) {
                $suggestionData['entity_id'] = $analysisResult['entity_id'];
            }
            if (!empty($analysisResult['entity_type'])) {
                $suggestionData['entity_type'] = $analysisResult['entity_type'];
            }
            
            // Also try to get product_id or customer_id from suggestion data
            if (!empty($suggestion['data']['product_id'])) {
                $suggestionData['product_id'] = $suggestion['data']['product_id'];
            }
            if (!empty($suggestion['data']['customer_id'])) {
                $suggestionData['customer_id'] = $suggestion['data']['customer_id'];
            }

            $actionData = [
                'analysis_id' => $analysisResult['id'] ?? null,
                'action_type' => $actionType,
                'action_data' => $suggestionData,
                'status' => $needsApproval ? 'pending' : 'approved',
                'priority_score' => $priority,
                'requires_approval' => $needsApproval ? 1 : 0,
            ];

            $actionId = $this->database->saveAction($actionData);
            if ($actionId) {
                $created++;
                appLogger("[AIAgent] Action created with ID: {$actionId}, type: {$actionType}");
            } else {
                appLogger("[AIAgent] Failed to save action of type: {$actionType}");
            }
        }

        appLogger("[AIAgent] Total actions created: {$created}");
        return $created;
    }

    /**
     * Execute approved actions automatically
     *
     * @return int Number of actions executed
     */
    private function executeApprovedActions()
    {
        if (!$this->actionExecutor) {
            appLogger("[AIAgent] ActionExecutor not available for auto-execute");
            return 0;
        }

        $limit = (int) $this->settings->get('actions_max_per_run', 10);
        $priorityThreshold = (int) $this->settings->get('analysis_priority_threshold', 70);
        
        // Get approved actions with high priority
        $actions = $this->database->getActions([
            'status' => 'approved',
        ], $limit, 0);

        $executed = 0;
        $errors = [];

        foreach ($actions as $action) {
            // Only auto-execute high priority actions
            if ($action['priority_score'] < $priorityThreshold) {
                continue;
            }

            try {
                $result = $this->actionExecutor->executeById($action['id']);
                if ($result['success']) {
                    $executed++;
                    appLogger("[AIAgent] Auto-executed action #{$action['id']} ({$action['action_type']})");
                } else {
                    $errors[] = "Action #{$action['id']}: " . ($result['error'] ?? 'Unknown error');
                    appLogger("[AIAgent] Failed to auto-execute action #{$action['id']}: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $errors[] = "Action #{$action['id']}: " . $e->getMessage();
                appLogger("[AIAgent] Exception auto-executing action #{$action['id']}: " . $e->getMessage());
            }
        }

        return $executed;
    }
}
