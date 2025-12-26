<?php
/**
 * AI Sales Agent Module Bootstrap
 * 
 * @package Forooshyar\Modules\AIAgent
 */

namespace Forooshyar\Modules\AIAgent;

use Forooshyar\WPLite\Container;
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
use Forooshyar\Modules\AIAgent\Services\ContextManager;
use Forooshyar\Modules\AIAgent\Services\ScheduledTaskService;
use Forooshyar\Modules\AIAgent\Services\LLM\LLMFactory;
use Forooshyar\Modules\AIAgent\Admin\AIAgentAdminController;
use Forooshyar\Modules\AIAgent\Admin\SettingsController;
use Forooshyar\Modules\AIAgent\Commands;
use Forooshyar\Modules\AIAgent\Database\Migrations;
use function Forooshyar\WPLite\appLogger;

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
        $this->initializeContextManager();
        $this->initializeScheduledTasks();

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
    }

    /**
     * Initialize context manager with default prompts
     *
     * @return void
     */
    private function initializeContextManager()
    {
        $contextManager = Container::resolve(ContextManager::class);
        $contextManager->initializeDefaults();
    }

    /**
     * Initialize scheduled task service
     *
     * @return void
     */
    private function initializeScheduledTasks()
    {
        $scheduledTaskService = Container::resolve(ScheduledTaskService::class);
        $scheduledTaskService->register();
    }

    /**
     * Load module configuration from appConfig
     *
     * @return void
     */
    private function loadConfig()
    {
        // Config is loaded from configs/aiagent.php via appConfig()
        $this->config = appConfig('aiagent', []);
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
                Container::resolve(DatabaseService::class),
                Container::resolve(ActionExecutor::class)
            );
        });

        // Context Manager - مدیریت پرامپت‌ها و قالب‌ها
        Container::bind(ContextManager::class, function () {
            return new ContextManager();
        });

        // Scheduled Task Service - مدیریت وظایف زمان‌بندی شده
        Container::bind(ScheduledTaskService::class, function () {
            return new ScheduledTaskService(
                Container::resolve(ActionExecutor::class),
                Container::resolve(SettingsManager::class),
                Container::resolve(Logger::class)
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
     * Register AJAX handlers
     * Note: AJAX routes are now defined in routes/ajax.php using AIAgentController
     * This method is kept for backward compatibility but does nothing
     *
     * @return void
     * @deprecated Use routes/ajax.php instead
     */
    public function registerAjaxHandlers()
    {
        // AJAX routes are now registered via routes/ajax.php
        // See AIAgentController for handler implementations
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
            'display' => __('دو بار در روز', 'forooshyar')
        ];
        
        // اضافه کردن بازه هر دقیقه برای heartbeat
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => __('هر دقیقه', 'forooshyar')
        ];
        
        // اضافه کردن بازه هر 30 ثانیه برای پردازش سریع‌تر
        $schedules['every_30_seconds'] = [
            'interval' => 30,
            'display' => __('هر 30 ثانیه', 'forooshyar')
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
}
