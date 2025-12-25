<?php
/**
 * AI Sales Agent Module Bootstrap
 * 
 * @package Forooshyar\Modules\AIAgent
 */

namespace Forooshyar\Modules\AIAgent;

use WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;
use Forooshyar\Modules\AIAgent\Services\AIAgentService;
use Forooshyar\Modules\AIAgent\Services\DatabaseService;
use Forooshyar\Modules\AIAgent\Services\CacheService;
use Forooshyar\Modules\AIAgent\Services\RateLimitService;
use Forooshyar\Modules\AIAgent\Services\ActionExecutor;
use Forooshyar\Modules\AIAgent\Services\ProductAnalyzer;
use Forooshyar\Modules\AIAgent\Services\CustomerAnalyzer;
use Forooshyar\Modules\AIAgent\Services\Logger;
use Forooshyar\Modules\AIAgent\Services\NotificationService;
use Forooshyar\Modules\AIAgent\Services\AnalysisJobManager;
use Forooshyar\Modules\AIAgent\Services\LLM\LLMFactory;
use Forooshyar\Modules\AIAgent\Admin\AIAgentAdminController;
use Forooshyar\Modules\AIAgent\Admin\SettingsController;
use Forooshyar\Modules\AIAgent\Commands;
use Forooshyar\Modules\AIAgent\Database\Migrations;

class AIAgentModule
{
    /** @var bool */
    private $booted = false;

    /** @var array */
    private $config = [];

    /**
     * Register module with container
     * Called during plugin registration phase
     *
     * @return void
     */
    public function register()
    {
        $this->loadConfig();
        $this->registerServices();
    }

    /**
     * Boot module services
     * Called during plugin boot phase
     *
     * @return void
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        // Always register admin pages (even if module disabled, for settings access)
        $this->registerAdminPages();
        
        // Register settings controller AJAX handlers
        $settingsController = Container::resolve(SettingsController::class);
        $settingsController->register();

        // Always register AJAX handlers for testing (test connection, run analysis)
        $this->registerAjaxHandlers();

        // Ensure database tables exist
        $this->ensureTablesExist();

        if (!$this->shouldActivate()) {
            $this->booted = true;
            return;
        }

        $this->registerHooks();
        $this->registerCommands();
        $this->initializeNotifications();

        $this->booted = true;

        do_action('aiagent_module_activated');
    }

    /**
     * Ensure database tables exist
     *
     * @return void
     */
    private function ensureTablesExist()
    {
        $db = Container::resolve(DatabaseService::class);
        if (!$db->checkTablesExist()) {
            $this->migrate();
        }
    }

    /**
     * Check if module should be active
     *
     * @return bool
     */
    public function shouldActivate()
    {
        return (bool) get_option('aiagent_module_enabled', true);
    }

    /**
     * Initialize notification scheduling
     *
     * @return void
     */
    private function initializeNotifications()
    {
        $notification = Container::resolve(NotificationService::class);
        $settings = Container::resolve(SettingsManager::class);

        if ($settings->isNotificationEnabled('daily_summary')) {
            $notification->scheduleDailySummary();
        } else {
            $notification->unscheduleDailySummary();
        }

        // Hook for daily summary
        add_action('aiagent_daily_summary', function () {
            $service = Container::resolve(AIAgentService::class);
            $notification = Container::resolve(NotificationService::class);
            $stats = $service->getStatistics(1);
            $notification->sendDailySummary($stats);
        });
    }    /**
     * Load module configuration
     *
     * @return void
     */
    private function loadConfig()
    {
        $configPath = __DIR__ . '/Config/ai-agent.php';
        if (file_exists($configPath)) {
            $this->config = require $configPath;
        }
    }

    /**
     * Register all module services with container
     *
     * @return void
     */
    private function registerServices()
    {
        // Settings Manager
        Container::bind(SettingsManager::class, function () {
            return new SettingsManager();
        });

        // Subscription Manager
        Container::bind(SubscriptionManager::class, function () {
            return new SubscriptionManager(
                Container::resolve(SettingsManager::class)
            );
        });

        // Logger
        Container::bind(Logger::class, function () {
            return new Logger(
                Container::resolve(SettingsManager::class)
            );
        });

        // Database Service
        Container::bind(DatabaseService::class, function () {
            return new DatabaseService();
        });

        // Cache Service
        Container::bind(CacheService::class, function () {
            return new CacheService(
                Container::resolve(SettingsManager::class)
            );
        });

        // Rate Limit Service
        Container::bind(RateLimitService::class, function () {
            return new RateLimitService(
                Container::resolve(SettingsManager::class),
                Container::resolve(CacheService::class)
            );
        });

        // Action Executor
        Container::bind(ActionExecutor::class, function () {
            return new ActionExecutor(
                Container::resolve(SettingsManager::class),
                Container::resolve(DatabaseService::class),
                Container::resolve(Logger::class)
            );
        });

        // Product Analyzer
        Container::bind(ProductAnalyzer::class, function () {
            $settings = Container::resolve(SettingsManager::class);
            $provider = LLMFactory::create(
                $settings->get('llm_provider', 'ollama'),
                $this->getLLMConfig($settings)
            );
            return new ProductAnalyzer(
                $provider,
                Container::resolve(DatabaseService::class),
                Container::resolve(SettingsManager::class),
                Container::resolve(Logger::class)
            );
        });

        // Customer Analyzer
        Container::bind(CustomerAnalyzer::class, function () {
            $settings = Container::resolve(SettingsManager::class);
            $provider = LLMFactory::create(
                $settings->get('llm_provider', 'ollama'),
                $this->getLLMConfig($settings)
            );
            return new CustomerAnalyzer(
                $provider,
                Container::resolve(DatabaseService::class),
                Container::resolve(SettingsManager::class),
                Container::resolve(Logger::class)
            );
        });

        // Main AI Agent Service
        Container::bind(AIAgentService::class, function () {
            return new AIAgentService(
                Container::resolve(SubscriptionManager::class),
                Container::resolve(SettingsManager::class),
                Container::resolve(ProductAnalyzer::class),
                Container::resolve(CustomerAnalyzer::class),
                Container::resolve(ActionExecutor::class),
                Container::resolve(DatabaseService::class),
                Container::resolve(Logger::class)
            );
        });

        // Admin Controller
        Container::bind(AIAgentAdminController::class, function () {
            return new AIAgentAdminController(
                Container::resolve(AIAgentService::class),
                Container::resolve(SubscriptionManager::class),
                Container::resolve(SettingsManager::class)
            );
        });

        // Notification Service
        Container::bind(NotificationService::class, function () {
            return new NotificationService(
                Container::resolve(SettingsManager::class),
                Container::resolve(Logger::class)
            );
        });

        // Settings Controller
        Container::bind(SettingsController::class, function () {
            return new SettingsController(
                Container::resolve(SettingsManager::class),
                Container::resolve(SubscriptionManager::class)
            );
        });

        // Analysis Job Manager
        Container::bind(AnalysisJobManager::class, function () {
            return new AnalysisJobManager(
                Container::resolve(ProductAnalyzer::class),
                Container::resolve(CustomerAnalyzer::class),
                Container::resolve(SubscriptionManager::class),
                Container::resolve(SettingsManager::class),
                Container::resolve(DatabaseService::class)
            );
        });
    }

    /**
     * Get LLM configuration from settings
     *
     * @param SettingsManager $settings
     * @return array
     */
    private function getLLMConfig($settings)
    {
        return [
            'endpoint' => $settings->get('llm_endpoint', 'http://localhost:11434/api/generate'),
            'api_key' => $settings->get('llm_api_key', ''),
            'model' => $settings->get('llm_model', 'llama2'),
            'temperature' => $settings->get('llm_temperature', 0.7),
            'max_tokens' => $settings->get('llm_max_tokens', 2000),
            'timeout' => $settings->get('llm_timeout', 60),
        ];
    }

    /**
     * Run database migrations
     *
     * @return void
     */
    public function migrate()
    {
        $migrations = new Migrations();
        $migrations->run();
    }

    /**
     * Register admin menus and pages
     *
     * @return void
     */
    public function registerAdminPages()
    {
        add_action('admin_menu', function () {
            $controller = Container::resolve(AIAgentAdminController::class);
            $controller->registerMenus();
        }, 35);

        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'ai-agent') !== false || strpos($hook, 'ai-settings') !== false) {
                $this->enqueueAdminAssets();
            }
        });
    }

    /**
     * Enqueue admin CSS and JS assets
     *
     * @return void
     */
    private function enqueueAdminAssets()
    {
        $baseUrl = plugin_dir_url(dirname(dirname(dirname(__DIR__))));

        wp_enqueue_style(
            'aiagent-admin',
            $baseUrl . 'assets/css/ai-agent-admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'aiagent-admin',
            $baseUrl . 'assets/js/ai-agent-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('aiagent-admin', 'aiagentAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiagent_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'forooshyar'),
                'error' => __('An error occurred', 'forooshyar'),
                'success' => __('Operation completed successfully', 'forooshyar'),
                'confirm' => __('Are you sure?', 'forooshyar'),
                'running' => __('Analysis running...', 'forooshyar'),
            ]
        ]);

        // Chart.js for statistics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );
    }

    /**
     * Register WP-CLI commands
     *
     * @return void
     */
    public function registerCommands()
    {
        if (!\defined('WP_CLI') || !WP_CLI) {
            return;
        }

        \WP_CLI::add_command('forooshyar ai', Commands\AIAgentCommand::class);
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    public function registerHooks()
    {
        // Cron schedules
        add_filter('cron_schedules', [$this, 'addCronSchedules']);
        add_action('aiagent_scheduled_analysis', [$this, 'runScheduledAnalysis']);
        
        // Job processing cron hook
        add_action(AnalysisJobManager::CRON_HOOK, [$this, 'processAnalysisJob']);

        // Activation/Deactivation
        register_activation_hook(
            dirname(dirname(dirname(__DIR__))) . '/forooshyar.php',
            [$this, 'onActivation']
        );
    }

    /**
     * Register AJAX handlers (always registered for testing)
     *
     * @return void
     */
    public function registerAjaxHandlers()
    {
        add_action('wp_ajax_aiagent_start_analysis', [$this, 'ajaxStartAnalysis']);
        add_action('wp_ajax_aiagent_cancel_analysis', [$this, 'ajaxCancelAnalysis']);
        add_action('wp_ajax_aiagent_get_analysis_progress', [$this, 'ajaxGetAnalysisProgress']);
        add_action('wp_ajax_aiagent_run_analysis', [$this, 'ajaxRunAnalysis']); // Keep for backward compatibility
        add_action('wp_ajax_aiagent_execute_action', [$this, 'ajaxExecuteAction']);
        add_action('wp_ajax_aiagent_approve_action', [$this, 'ajaxApproveAction']);
        add_action('wp_ajax_aiagent_get_stats', [$this, 'ajaxGetStats']);
        add_action('wp_ajax_aiagent_test_connection', [$this, 'ajaxTestConnection']);
    }

    /**
     * Process analysis job (called by cron)
     *
     * @return void
     */
    public function processAnalysisJob()
    {
        $jobManager = Container::resolve(AnalysisJobManager::class);
        $jobManager->processNextBatch();
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules
     * @return array
     */
    public function addCronSchedules($schedules)
    {
        $schedules['twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Twice Daily', 'forooshyar')
        ];
        return $schedules;
    }

    /**
     * Run scheduled analysis
     *
     * @return void
     */
    public function runScheduledAnalysis()
    {
        $service = Container::resolve(AIAgentService::class);
        $service->runAnalysis();
    }

    /**
     * Handle plugin activation
     *
     * @return void
     */
    public function onActivation()
    {
        $this->migrate();
        $this->scheduleEvents();
    }

    /**
     * Schedule cron events
     *
     * @return void
     */
    private function scheduleEvents()
    {
        $settings = Container::resolve(SettingsManager::class);
        $frequency = $settings->get('schedule_frequency', 'daily');

        if (!wp_next_scheduled('aiagent_scheduled_analysis')) {
            wp_schedule_event(time(), $frequency, 'aiagent_scheduled_analysis');
        }
    }

    // AJAX Handlers

    /**
     * AJAX: Run analysis
     *
     * @return void
     */
    public function ajaxRunAnalysis()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        // Check if database tables exist
        $db = Container::resolve(DatabaseService::class);
        if (!$db->checkTablesExist()) {
            $this->migrate();
        }
        
        $service = Container::resolve(AIAgentService::class);

        try {
            $result = $service->runAnalysis($type);
            
            // Build detailed message
            $productsAnalyzed = isset($result['products']['analyzed']) ? $result['products']['analyzed'] : 0;
            $productsTotal = isset($result['products']['total']) ? $result['products']['total'] : 0;
            $productsErrors = isset($result['products']['errors']) ? $result['products']['errors'] : [];
            $customersAnalyzed = isset($result['customers']['analyzed']) ? $result['customers']['analyzed'] : 0;
            $customersTotal = isset($result['customers']['total']) ? $result['customers']['total'] : 0;
            
            // Collect all errors from products analysis
            if (!empty($productsErrors)) {
                foreach ($productsErrors as $err) {
                    $errMsg = is_array($err) ? (isset($err['error']) ? $err['error'] : json_encode($err)) : $err;
                    $result['errors'][] = sprintf(__('محصول %s: %s', 'forooshyar'), 
                        isset($err['product_id']) ? $err['product_id'] : '?', 
                        $errMsg
                    );
                }
            }
            
            if ($result['success']) {
                if ($productsTotal === 0 && $customersTotal === 0) {
                    $message = __('تحلیل انجام شد اما محصول یا مشتری برای تحلیل یافت نشد.', 'forooshyar');
                } elseif ($productsAnalyzed === 0 && $productsTotal > 0) {
                    $message = sprintf(
                        __('تحلیل انجام شد اما هیچ محصولی با موفقیت تحلیل نشد (%d محصول یافت شد). خطاها را بررسی کنید.', 'forooshyar'),
                        $productsTotal
                    );
                } else {
                    $message = sprintf(
                        __('تحلیل با موفقیت انجام شد. محصولات: %d از %d، مشتریان: %d از %d، اقدامات ایجاد شده: %d', 'forooshyar'),
                        $productsAnalyzed,
                        $productsTotal,
                        $customersAnalyzed,
                        $customersTotal,
                        $result['actions_created']
                    );
                }
                $result['message'] = $message;
            } else {
                // Include errors in message
                $errorMessages = !empty($result['errors']) ? implode(', ', $result['errors']) : __('خطای ناشناخته', 'forooshyar');
                $result['message'] = __('خطا در تحلیل: ', 'forooshyar') . $errorMessages;
            }
            
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Execute action
     *
     * @return void
     */
    public function ajaxExecuteAction()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $actionId = isset($_POST['action_id']) ? absint($_POST['action_id']) : 0;
        $executor = Container::resolve(ActionExecutor::class);

        try {
            $result = $executor->executeById($actionId);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Approve action
     *
     * @return void
     */
    public function ajaxApproveAction()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $actionId = isset($_POST['action_id']) ? absint($_POST['action_id']) : 0;
        $db = Container::resolve(DatabaseService::class);

        try {
            $db->approveAction($actionId, get_current_user_id());
            do_action('aiagent_action_approved', $actionId, get_current_user_id());
            wp_send_json_success(['message' => __('اقدام تأیید شد', 'forooshyar')]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Get statistics
     *
     * @return void
     */
    public function ajaxGetStats()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        $service = Container::resolve(AIAgentService::class);

        try {
            $stats = $service->getStatistics($days);
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Test LLM connection
     *
     * @return void
     */
    public function ajaxTestConnection()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $service = Container::resolve(AIAgentService::class);

        try {
            $result = $service->testConnection();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Start async analysis job
     *
     * @return void
     */
    public function ajaxStartAnalysis()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';

        // Check if database tables exist
        $db = Container::resolve(DatabaseService::class);
        if (!$db->checkTablesExist()) {
            $this->migrate();
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $result = $jobManager->startJob($type);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            appLogger("[AIAgent] Start analysis error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Cancel running analysis job
     *
     * @return void
     */
    public function ajaxCancelAnalysis()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $result = $jobManager->cancelJob();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            appLogger("[AIAgent] Cancel analysis error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Get analysis job progress
     *
     * @return void
     */
    public function ajaxGetAnalysisProgress()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $progress = $jobManager->getJobProgress();
            wp_send_json_success($progress);
        } catch (\Exception $e) {
            appLogger("[AIAgent] Get progress error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

}
