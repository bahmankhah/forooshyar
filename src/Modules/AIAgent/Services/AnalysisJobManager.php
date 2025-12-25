<?php
/**
 * Analysis Job Manager
 * 
 * Manages async analysis jobs with progress tracking and cancellation support.
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

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

    /**
     * @param ProductAnalyzer $productAnalyzer
     * @param CustomerAnalyzer $customerAnalyzer
     * @param SubscriptionManager $subscription
     * @param SettingsManager $settings
     * @param DatabaseService $database
     */
    public function __construct(
        ProductAnalyzer $productAnalyzer,
        CustomerAnalyzer $customerAnalyzer,
        SubscriptionManager $subscription,
        SettingsManager $settings,
        DatabaseService $database
    ) {
        $this->productAnalyzer = $productAnalyzer;
        $this->customerAnalyzer = $customerAnalyzer;
        $this->subscription = $subscription;
        $this->settings = $settings;
        $this->database = $database;
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

        // Get entities to analyze
        $products = [];
        $customers = [];

        if ($type === 'all' || $type === 'products') {
            if ($this->subscription->isFeatureEnabled(SubscriptionManager::FEATURE_PRODUCT_ANALYSIS)) {
                $limit = $this->settings->get('analysis_product_limit', 50);
                $products = $this->productAnalyzer->getEntities($limit);
            }
        }

        if ($type === 'all' || $type === 'customers') {
            if ($this->subscription->isFeatureEnabled(SubscriptionManager::FEATURE_CUSTOMER_ANALYSIS)) {
                $limit = $this->settings->get('analysis_customer_limit', 100);
                $customers = $this->customerAnalyzer->getEntities($limit);
            }
        }

        $totalItems = count($products) + count($customers);
        
        if ($totalItems === 0) {
            return [
                'success' => false,
                'error' => __('هیچ موردی برای تحلیل یافت نشد', 'forooshyar'),
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
            $this->saveJobState($jobState);
            
            // Increment usage
            $this->subscription->incrementUsage('analyses_per_day');
            
            // Save analysis run record
            $this->database->saveAnalysisRun([
                'type' => $jobState['type'],
                'success' => true,
                'products_analyzed' => $jobState['products_success'],
                'customers_analyzed' => $jobState['customers_success'],
                'actions_created' => $jobState['actions_created'],
                'duration_ms' => 0,
            ]);
            
            appLogger("[AIAgent] Job completed: {$jobState['id']} - Products: {$jobState['products_success']}/{$jobState['products_total']}, Customers: {$jobState['customers_success']}/{$jobState['customers_total']}");
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

        return [
            'status' => $state['status'],
            'job_id' => $state['id'] ?? null,
            'is_running' => $state['status'] === self::STATUS_RUNNING,
            'is_cancelling' => $state['status'] === self::STATUS_CANCELLING,
            'percentage' => $percentage,
            'products' => [
                'total' => $state['products_total'],
                'processed' => $state['products_processed'],
                'success' => $state['products_success'],
                'failed' => $state['products_failed'],
            ],
            'customers' => [
                'total' => $state['customers_total'],
                'processed' => $state['customers_processed'],
                'success' => $state['customers_success'],
                'failed' => $state['customers_failed'],
            ],
            'actions_created' => $state['actions_created'],
            'current_item' => $state['current_item'],
            'errors' => array_slice($state['errors'], -5), // Last 5 errors
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

            $actionData = [
                'analysis_id' => $analysisResult['id'] ?? null,
                'action_type' => $actionType,
                'action_data' => $suggestion['data'] ?? [],
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
}
