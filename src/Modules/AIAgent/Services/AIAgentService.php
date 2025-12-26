<?php
/**
 * AI Agent Service - Main Orchestrator
 * 
 * Central service for coordinating AI-powered sales analysis and actions.
 * Handles analysis scheduling, action creation, and module orchestration.
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use Forooshyar\Modules\AIAgent\Services\LLM\LLMFactory;
use Forooshyar\Modules\AIAgent\Exceptions\SubscriptionLimitException;
use Forooshyar\Modules\AIAgent\Exceptions\FeatureDisabledException;
use Forooshyar\Modules\AIAgent\Exceptions\LLMConnectionException;

class AIAgentService
{
    /** @var SubscriptionManager */
    private $subscription;

    /** @var SettingsManager */
    private $settings;

    /** @var ProductAnalyzer */
    private $productAnalyzer;

    /** @var CustomerAnalyzer */
    private $customerAnalyzer;

    /** @var ActionExecutor */
    private $actionExecutor;

    /** @var DatabaseService */
    private $database;

    /** @var Logger */
    private $logger;

    /** @var array Analysis run context */
    private $runContext = [];

    /**
     * @param SubscriptionManager $subscription
     * @param SettingsManager $settings
     * @param ProductAnalyzer $productAnalyzer
     * @param CustomerAnalyzer $customerAnalyzer
     * @param ActionExecutor $actionExecutor
     * @param DatabaseService $database
     * @param Logger $logger
     */
    public function __construct(
        SubscriptionManager $subscription,
        SettingsManager $settings,
        ProductAnalyzer $productAnalyzer,
        CustomerAnalyzer $customerAnalyzer,
        ActionExecutor $actionExecutor,
        DatabaseService $database,
        Logger $logger
    ) {
        $this->subscription = $subscription;
        $this->settings = $settings;
        $this->productAnalyzer = $productAnalyzer;
        $this->customerAnalyzer = $customerAnalyzer;
        $this->actionExecutor = $actionExecutor;
        $this->database = $database;
        $this->logger = $logger;
    }

    /**
     * Run complete analysis cycle
     *
     * @param string $type 'all', 'products', or 'customers'
     * @param array $options Additional options
     * @return array Results with counts and errors
     */
    public function runAnalysis($type = 'all', array $options = [])
    {
        $this->initRunContext($type, $options);
        $this->logger->info('Starting analysis', ['type' => $type, 'options' => $options]);

        do_action('aiagent_before_analysis', $type, $options);

        $results = [
            'success' => true,
            'type' => $type,
            'products' => null,
            'customers' => null,
            'actions_created' => 0,
            'actions_executed' => 0,
            'errors' => [],
            'warnings' => [],
            'timestamp' => current_time('mysql'),
            'duration_ms' => 0,
        ];

        $startTime = microtime(true);

        try {
            // Pre-flight checks
            $this->validateAnalysisPrerequisites($type);

            // Run product analysis
            if ($type === 'all' || $type === 'products') {
                if ($this->subscription->isFeatureEnabled(SubscriptionManager::FEATURE_PRODUCT_ANALYSIS)) {
                    $results['products'] = $this->analyzeProducts($options);
                    $results['actions_created'] += $this->runContext['actions_created'];
                } else {
                    $results['warnings'][] = __('قابلیت تحلیل محصولات در اشتراک شما فعال نیست', 'forooshyar');
                }
            }

            // Run customer analysis
            if ($type === 'all' || $type === 'customers') {
                if ($this->subscription->isFeatureEnabled(SubscriptionManager::FEATURE_CUSTOMER_ANALYSIS)) {
                    $results['customers'] = $this->analyzeCustomers($options);
                    $results['actions_created'] += $this->runContext['actions_created'];
                } else {
                    $results['warnings'][] = __('قابلیت تحلیل مشتریان در اشتراک شما فعال نیست', 'forooshyar');
                }
            }

            // Increment usage
            $this->subscription->incrementUsage('analyses_per_day');

            // Auto-execute approved actions if enabled
            if ($this->settings->get('actions_auto_execute', false)) {
                $executeResult = $this->executeApprovedActions();
                $results['actions_executed'] = $executeResult['executed'];
                if (!empty($executeResult['errors'])) {
                    $results['warnings'] = array_merge($results['warnings'], $executeResult['errors']);
                }
            }

            do_action('aiagent_after_analysis', $type, $results);

        } catch (FeatureDisabledException $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->warning('Analysis blocked: feature disabled', ['error' => $e->getMessage()]);
        } catch (SubscriptionLimitException $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->warning('Analysis blocked: limit exceeded', ['error' => $e->getMessage()]);
        } catch (LLMConnectionException $e) {
            $results['success'] = false;
            $results['errors'][] = 'LLM connection failed: ' . $e->getMessage();
            $this->logger->error('LLM connection failed', ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->error('Analysis failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            do_action('aiagent_analysis_failed', $type, $e);
        }

        $results['duration_ms'] = round((microtime(true) - $startTime) * 1000);

        // Save analysis run record
        $this->saveAnalysisRun($results);

        return $results;
    }

    /**
     * Initialize run context
     *
     * @param string $type
     * @param array $options
     * @return void
     */
    private function initRunContext($type, array $options)
    {
        $this->runContext = [
            'type' => $type,
            'options' => $options,
            'start_time' => microtime(true),
            'actions_created' => 0,
            'suggestions_processed' => 0,
        ];
    }

    /**
     * Validate prerequisites before running analysis
     *
     * @param string $type
     * @return void
     * @throws FeatureDisabledException
     * @throws SubscriptionLimitException
     * @throws LLMConnectionException
     */
    private function validateAnalysisPrerequisites($type)
    {
        // Check module enabled
        if (!$this->subscription->isModuleEnabled()) {
            throw new FeatureDisabledException(__('ماژول دستیار هوشمند فعال نیست', 'forooshyar'));
        }

        // Check usage limits
        if ($this->subscription->isLimitExceeded('analyses_per_day')) {
            throw new SubscriptionLimitException(__('محدودیت تحلیل روزانه به پایان رسیده است', 'forooshyar'));
        }

        // Validate LLM configuration
        $provider = $this->settings->get('llm_provider', 'ollama');
        $validation = LLMFactory::validateConfig($provider, [
            'endpoint' => $this->settings->get('llm_endpoint'),
            'api_key' => $this->settings->get('llm_api_key'),
        ]);

        if (!$validation['valid']) {
            throw new LLMConnectionException(__('پیکربندی LLM نامعتبر است: ', 'forooshyar') . implode(', ', $validation['errors']));
        }

        // Check if within preferred hours (optional)
        if ($this->settings->get('schedule_frequency') !== 'manual') {
            $currentHour = (int) current_time('G');
            $avoidHours = $this->settings->get('schedule_avoid_hours', []);
            if (\in_array($currentHour, $avoidHours, true)) {
                $this->logger->info('Analysis running outside preferred hours', ['hour' => $currentHour]);
            }
        }
    }

    /**
     * Run product analysis only
     *
     * @param array $options
     * @return array
     */
    public function analyzeProducts(array $options = [])
    {
        $this->logger->info('Starting product analysis');

        $subscriptionLimit = $this->subscription->getLimit('products_per_analysis');
        $settingsLimit = $this->settings->get('analysis_product_limit', 50);
        $limit = $subscriptionLimit === -1 ? $settingsLimit : min($settingsLimit, $subscriptionLimit);

        if (isset($options['limit'])) {
            $limit = min($limit, (int) $options['limit']);
        }

        $analyzerOptions = array_merge($options, ['limit' => $limit]);

        // Add priority filter if specified
        if (isset($options['min_priority'])) {
            $analyzerOptions['min_priority'] = (int) $options['min_priority'];
        }

        $results = $this->productAnalyzer->analyze($analyzerOptions);

        // Create actions from suggestions
        $actionsCreated = $this->createActionsFromSuggestions($results, 'product');
        $this->runContext['actions_created'] = $actionsCreated;

        $results['actions_created'] = $actionsCreated;

        return $results;
    }

    /**
     * Run customer analysis only
     *
     * @param array $options
     * @return array
     */
    public function analyzeCustomers(array $options = [])
    {
        $this->logger->info('Starting customer analysis');

        $subscriptionLimit = $this->subscription->getLimit('customers_per_analysis');
        $settingsLimit = $this->settings->get('analysis_customer_limit', 100);
        $limit = $subscriptionLimit === -1 ? $settingsLimit : min($settingsLimit, $subscriptionLimit);

        if (isset($options['limit'])) {
            $limit = min($limit, (int) $options['limit']);
        }

        $analyzerOptions = array_merge($options, ['limit' => $limit]);

        // Add segment filter if specified
        if (isset($options['segment'])) {
            $analyzerOptions['segment'] = $options['segment'];
        }

        $results = $this->customerAnalyzer->analyze($analyzerOptions);

        // Create actions from suggestions
        $actionsCreated = $this->createActionsFromSuggestions($results, 'customer');
        $this->runContext['actions_created'] = $actionsCreated;

        $results['actions_created'] = $actionsCreated;

        return $results;
    }

    /**
     * Analyze a single entity
     *
     * @param string $type 'product' or 'customer'
     * @param int $entityId
     * @return array
     */
    public function analyzeSingle($type, $entityId)
    {
        $this->logger->info("Analyzing single {$type}", ['id' => $entityId]);

        if (!$this->subscription->isModuleEnabled()) {
            return ['success' => false, 'error' => __('ماژول فعال نیست', 'forooshyar')];
        }

        $analyzer = $type === 'product' ? $this->productAnalyzer : $this->customerAnalyzer;
        $result = $analyzer->analyzeEntity($entityId);

        if ($result['success'] && !empty($result['suggestions'])) {
            $this->createActionsFromSuggestions(['suggestions' => $result['suggestions'], 'id' => $result['id']], $type);
        }

        return $result;
    }

    /**
     * Create actions from analysis suggestions
     *
     * @param array $analysisResults
     * @param string $sourceType
     * @return int Number of actions created
     */
    private function createActionsFromSuggestions(array $analysisResults, $sourceType = 'unknown')
    {
        if (empty($analysisResults['suggestions'])) {
            return 0;
        }

        $enabledActions = $this->settings->get('actions_enabled_types', []);

        $created = 0;

        foreach ($analysisResults['suggestions'] as $suggestion) {
            $actionType = isset($suggestion['type']) ? $suggestion['type'] : '';

            // Skip if action type not enabled
            if (!\in_array($actionType, $enabledActions, true)) {
                $this->logger->debug('Skipping disabled action type', ['type' => $actionType]);
                continue;
            }

            $priority = isset($suggestion['priority']) ? (int) $suggestion['priority'] : 50;

            // Include reasoning in action_data for display in dashboard
            $suggestionData = isset($suggestion['data']) ? $suggestion['data'] : [];
            if (!empty($suggestion['reasoning'])) {
                $suggestionData['reasoning'] = $suggestion['reasoning'];
            }

            // All actions are created as 'pending' - no approval step needed
            // Users can execute any pending action directly
            $actionData = [
                'analysis_id' => isset($analysisResults['id']) ? $analysisResults['id'] : null,
                'action_type' => $actionType,
                'action_data' => $suggestionData,
                'status' => 'pending',
                'priority_score' => $priority,
                'requires_approval' => 0,
                'source_type' => $sourceType,
            ];

            $actionId = $this->database->saveAction($actionData);

            if ($actionId) {
                $created++;
                do_action('aiagent_action_created', $actionId, $actionData);
            }

            $this->runContext['suggestions_processed']++;
        }

        return $created;
    }

    /**
     * Execute pending actions with high priority (auto-execute)
     *
     * @param int|null $limit
     * @return array
     */
    private function executeApprovedActions($limit = null)
    {
        if ($limit === null) {
            $limit = $this->settings->get('actions_max_per_run', 10);
        }

        $priorityThreshold = (int) $this->settings->get('analysis_priority_threshold', 70);
        
        // Get pending actions (not just approved, since we removed approval step)
        $actions = $this->database->getActions(['status' => 'pending'], $limit);

        $executed = 0;
        $errors = [];

        foreach ($actions as $action) {
            // Only auto-execute actions with high priority
            if ($action['priority_score'] < $priorityThreshold) {
                continue;
            }
            
            // Check action limit
            $usageLimit = $this->subscription->checkUsageLimit('actions_per_day');
            if ($usageLimit['remaining'] <= 0 && $usageLimit['allowed'] !== -1) {
                $errors[] = __('محدودیت اقدامات روزانه به پایان رسیده است', 'forooshyar');
                break;
            }

            try {
                $result = $this->actionExecutor->executeById($action['id']);
                if ($result['success']) {
                    $executed++;
                    $this->subscription->incrementUsage('actions_per_day');
                } else {
                    $errors[] = sprintf(__('اقدام %d: ', 'forooshyar'), $action['id']) . ($result['message'] ?: __('خطای ناشناخته', 'forooshyar'));
                }
            } catch (\Exception $e) {
                $errors[] = sprintf(__('اقدام %d: ', 'forooshyar'), $action['id']) . $e->getMessage();
            }
        }

        return [
            'executed' => $executed,
            'errors' => $errors,
        ];
    }

    /**
     * Execute pending actions (public method)
     *
     * @param int|null $limit
     * @return array
     */
    public function executeActions($limit = null)
    {
        if ($limit === null) {
            $limit = $this->settings->get('actions_max_per_run', 10);
        }

        // Check subscription
        if (!$this->subscription->isFeatureEnabled(SubscriptionManager::FEATURE_AUTO_ACTIONS)) {
            return [
                'success' => false,
                'error' => __('قابلیت اجرای خودکار اقدامات در اشتراک شما فعال نیست', 'forooshyar'),
                'executed' => 0,
            ];
        }

        // Check usage limits
        $usageLimit = $this->subscription->checkUsageLimit('actions_per_day');
        if ($usageLimit['remaining'] <= 0 && $usageLimit['allowed'] !== -1) {
            return [
                'success' => false,
                'error' => __('محدودیت اقدامات روزانه به پایان رسیده است', 'forooshyar'),
                'executed' => 0,
            ];
        }

        // Get approved actions
        $actions = $this->database->getActions(['status' => 'approved'], $limit);

        $executed = 0;
        $failed = 0;
        $errors = [];

        foreach ($actions as $action) {
            try {
                $result = $this->actionExecutor->executeById($action['id']);
                if ($result['success']) {
                    $executed++;
                    $this->subscription->incrementUsage('actions_per_day');
                } else {
                    $failed++;
                    $errors[] = $result['message'] ?: $result['error'] ?: __('خطای ناشناخته', 'forooshyar');
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        return [
            'success' => $failed === 0,
            'executed' => $executed,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Save analysis run record
     *
     * @param array $results
     * @return void
     */
    private function saveAnalysisRun(array $results)
    {
        $this->database->saveAnalysisRun([
            'type' => $results['type'],
            'success' => $results['success'] ? 1 : 0,
            'products_analyzed' => isset($results['products']['analyzed']) ? $results['products']['analyzed'] : 0,
            'customers_analyzed' => isset($results['customers']['analyzed']) ? $results['customers']['analyzed'] : 0,
            'actions_created' => $results['actions_created'],
            'actions_executed' => $results['actions_executed'],
            'errors' => wp_json_encode($results['errors']),
            'duration_ms' => $results['duration_ms'],
            'created_at' => $results['timestamp'],
        ]);
    }

    /**
     * Get analysis statistics
     *
     * @param int $days
     * @return array
     */
    public function getStatistics($days = 30)
    {
        $stats = $this->database->getStatistics($days);

        // Add subscription info
        $stats['subscription'] = [
            'tier' => $this->subscription->getSubscriptionTier(),
            'tier_name' => $this->subscription->getTierDisplayName(),
            'features' => $this->subscription->getEnabledFeatures(),
            'usage' => [
                'analyses' => $this->subscription->checkUsageLimit('analyses_per_day'),
                'actions' => $this->subscription->checkUsageLimit('actions_per_day'),
            ],
        ];

        // Add summary stats
        $stats['summary'] = [
            'pending_actions' => $this->database->getPendingActionsCount(),
            'completed_today' => $this->database->getTodayCompletedCount(),
            'success_rate' => $this->database->getSuccessRate($days),
            'analyses_today' => $this->database->getTodayAnalysesCount(),
            'total_analyses' => $this->database->getTotalAnalysesCount(),
            'total_actions' => $this->database->getTotalActionsCount(),
        ];

        // Add trend data
        $stats['trends'] = [
            'analyses_by_day' => $this->database->getAnalysesByDay($days),
            'actions_by_type' => $this->database->getActionsByType($days),
            'success_by_day' => $this->database->getSuccessByDay($days),
        ];

        // Add LLM stats
        $stats['llm'] = [
            'provider' => $this->settings->get('llm_provider'),
            'model' => $this->settings->get('llm_model'),
            'total_tokens' => $this->database->getTotalTokensUsed($days),
            'avg_response_time' => $this->database->getAvgResponseTime($days),
        ];

        // Build daily data for chart (merge analyses and actions by date)
        $analysesByDay = [];
        foreach ($stats['trends']['analyses_by_day'] as $row) {
            $analysesByDay[$row['date']] = (int) $row['count'];
        }
        
        $actionsByDay = [];
        foreach ($stats['actions_daily'] as $row) {
            $actionsByDay[$row['date']] = (int) $row['count'];
        }
        
        // Generate daily array for last N days
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $daily[] = [
                'date' => $date,
                'analyses' => isset($analysesByDay[$date]) ? $analysesByDay[$date] : 0,
                'actions' => isset($actionsByDay[$date]) ? $actionsByDay[$date] : 0,
            ];
        }
        $stats['daily'] = $daily;

        return $stats;
    }

    /**
     * Get dashboard data
     *
     * @return array
     */
    public function getDashboardData()
    {
        return [
            'status' => $this->getStatus(),
            'quick_stats' => [
                'pending_actions' => $this->database->getPendingActionsCount(),
                'analyses_today' => $this->database->getTodayAnalysesCount(),
                'actions_today' => $this->database->getTodayActionsCount(),
                'success_rate' => $this->database->getSuccessRate(7),
            ],
            'recent_analyses' => $this->database->getRecentAnalyses(10),
            'recent_actions' => $this->database->getRecentActions(20),
            'usage' => [
                'analyses' => $this->subscription->checkUsageLimit('analyses_per_day'),
                'actions' => $this->subscription->checkUsageLimit('actions_per_day'),
            ],
        ];
    }

    /**
     * Test LLM connection
     *
     * @param string|null $provider Override provider
     * @param array $config Override config
     * @return array
     */
    public function testConnection($provider = null, array $config = [])
    {
        $provider = $provider ?: $this->settings->get('llm_provider', 'ollama');
        $config = array_merge([
            'endpoint' => $this->settings->get('llm_endpoint'),
            'api_key' => $this->settings->get('llm_api_key'),
            'model' => $this->settings->get('llm_model'),
        ], $config);

        try {
            $llm = LLMFactory::create($provider, $config);
            $result = $llm->testConnection();
            $result['provider'] = $provider;
            $result['model'] = $config['model'];
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'provider' => $provider,
            ];
        }
    }

    /**
     * Get available LLM models for current provider
     *
     * @return array
     */
    public function getAvailableModels()
    {
        $provider = $this->settings->get('llm_provider', 'ollama');
        $config = [
            'endpoint' => $this->settings->get('llm_endpoint'),
            'api_key' => $this->settings->get('llm_api_key'),
        ];

        return LLMFactory::getModelsForProvider($provider, $config);
    }

    /**
     * Get module status
     *
     * @return array
     */
    public function getStatus()
    {
        $connectionTest = $this->testConnection();

        return [
            'enabled' => $this->subscription->isModuleEnabled(),
            'tier' => $this->subscription->getSubscriptionTier(),
            'tier_name' => $this->subscription->getTierDisplayName(),
            'features' => $this->subscription->getEnabledFeatures(),
            'llm_provider' => $this->settings->get('llm_provider'),
            'llm_model' => $this->settings->get('llm_model'),
            'llm_connected' => $connectionTest['success'],
            'llm_message' => isset($connectionTest['message']) ? $connectionTest['message'] : '',
            'schedule' => $this->settings->get('schedule_frequency'),
            'next_scheduled' => wp_next_scheduled('aiagent_scheduled_analysis'),
        ];
    }

    /**
     * Get health check data
     *
     * @return array
     */
    public function getHealthCheck()
    {
        $checks = [];

        // Module enabled
        $checks['module_enabled'] = [
            'status' => $this->subscription->isModuleEnabled() ? 'ok' : 'warning',
            'message' => $this->subscription->isModuleEnabled() ? 'Module is enabled' : 'Module is disabled',
        ];

        // LLM connection
        $llmTest = $this->testConnection();
        $checks['llm_connection'] = [
            'status' => $llmTest['success'] ? 'ok' : 'error',
            'message' => $llmTest['message'],
        ];

        // Database tables
        $tablesExist = $this->database->checkTablesExist();
        $checks['database'] = [
            'status' => $tablesExist ? 'ok' : 'error',
            'message' => $tablesExist ? 'Database tables exist' : 'Database tables missing',
        ];

        // Usage limits
        $analysisUsage = $this->subscription->checkUsageLimit('analyses_per_day');
        $checks['analysis_limit'] = [
            'status' => $analysisUsage['remaining'] > 0 || $analysisUsage['allowed'] === -1 ? 'ok' : 'warning',
            'message' => sprintf('%d/%d analyses used today', $analysisUsage['used'], $analysisUsage['allowed']),
        ];

        // Cron scheduled
        $nextRun = wp_next_scheduled('aiagent_scheduled_analysis');
        $checks['cron'] = [
            'status' => $nextRun ? 'ok' : 'warning',
            'message' => $nextRun ? 'Next run: ' . date('Y-m-d H:i:s', $nextRun) : 'No scheduled run',
        ];

        // Overall status
        $hasError = false;
        $hasWarning = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $hasError = true;
            }
            if ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }

        return [
            'overall' => $hasError ? 'error' : ($hasWarning ? 'warning' : 'ok'),
            'checks' => $checks,
            'timestamp' => current_time('mysql'),
        ];
    }
}
