<?php

namespace Forooshyar\Providers;

use Forooshyar\Services\ProductService;
use Forooshyar\Services\ConfigService;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\CacheInvalidationService;
use Forooshyar\Services\LoggingService;
use Forooshyar\Services\ErrorHandlingService;
use Forooshyar\Services\TitleBuilder;
use Forooshyar\Services\ApiLogService;
use Forooshyar\Services\LogCleanupService;
use Forooshyar\Modules\AIAgent\AIAgentModule;
use Forooshyar\WPLite\Container;
use Forooshyar\WPLite\Provider;
use function Forooshyar\WPLite\appLogger;


class AppServiceProvider extends Provider
{
    /** @var AIAgentModule|null */
    private $aiAgentModule = null;

    public function register() {
        
        // Register AI Agent Module
        $this->registerAIAgentModule();
    }
    
    /**
     * Register AI Agent Module
     *
     * @return void
     */
    private function registerAIAgentModule()
    {
        if (class_exists(AIAgentModule::class)) {
            $this->aiAgentModule = new AIAgentModule();
            $this->aiAgentModule->register();
            appLogger('Forooshyar: AI Agent Module registered');
        }
    }
    
    public function bootEarly() {
    }
    
    public function admin() {
        // Admin initialization - register hooks but don't execute translation-dependent code yet
    }
    
    public function onInit() {
        
        // Load Persian text domain - moved to plugins_loaded hook for WordPress 6.5+ compatibility
        add_action('plugins_loaded', function() {
            load_plugin_textdomain(
                'forooshyar',
                false,
                dirname(plugin_basename(dirname(dirname(__DIR__)))) . '/languages'
            );
            appLogger('Forooshyar: Text domain loaded successfully');
            
            // Set Persian locale if not already set - moved to init hook
            add_action('init', function() {
                appLogger('Forooshyar: init hook - Setting up Persian locale features');
                if (get_locale() === 'fa_IR') {
                    appLogger('Forooshyar: Persian locale detected, setting up filters');
                    // Ensure Persian number formatting
                    add_filter('number_format_i18n', function($formatted, $number, $decimals) {
                        if (get_locale() === 'fa_IR') {
                            // Convert English digits to Persian
                            $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                            $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                            return str_replace($english_digits, $persian_digits, $formatted);
                        }
                        return $formatted;
                    }, 10, 3);
                    
                    // Set RTL direction for admin
                    add_filter('locale_stylesheet_uri', function($stylesheet_uri, $stylesheet_dir_uri) {
                        if (is_admin() && strpos($_SERVER['REQUEST_URI'], 'forooshyar') !== false) {
                            return $stylesheet_dir_uri . '/rtl.css';
                        }
                        return $stylesheet_uri;
                    }, 10, 2);
                }
            });
        });
        
        // Register admin menu - moved from admin() method to ensure translations are loaded
        add_action('admin_menu', function() {
            $adminController = new \Forooshyar\Controllers\AdminController();
            $adminController->registerAdminMenu();
        }, 30);
        
        // Enqueue admin scripts and styles - moved from admin() method
        add_action('admin_enqueue_scripts', function($hook) {
            appLogger('Forooshyar: admin_enqueue_scripts hook - Hook: ' . $hook);
            if (strpos($hook, 'forooshyar') !== false) {
                appLogger('Forooshyar: Enqueueing admin assets for forooshyar pages');
                // Enqueue Persian RTL styles
                wp_enqueue_style(
                    'forooshyar-admin-rtl',
                    plugin_dir_url(dirname(dirname(__DIR__))) . 'assets/css/admin-rtl.css',
                    [],
                    '1.0.0'
                );
                
                // Enqueue Persian JavaScript utilities
                wp_enqueue_script(
                    'forooshyar-admin-persian',
                    plugin_dir_url(dirname(dirname(__DIR__))) . 'assets/js/admin-persian.js',
                    ['jquery'],
                    '1.0.0',
                    true
                );
                
                // Localize script with Persian settings
                wp_localize_script('forooshyar-admin-persian', 'forooshyarAdmin', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('forooshyar_admin'),
                    'locale' => get_locale(),
                    'isRTL' => is_rtl(),
                    'dateFormat' => get_option('date_format'),
                    'timeFormat' => get_option('time_format'),
                    'strings' => [
                        'loading' => __('در حال بارگذاری...', 'forooshyar'),
                        'error' => __('خطا در ارتباط با سرور', 'forooshyar'),
                        'success' => __('عملیات با موفقیت انجام شد', 'forooshyar'),
                        'confirm' => __('آیا مطمئن هستید؟', 'forooshyar'),
                        'cancel' => __('لغو', 'forooshyar'),
                        'save' => __('ذخیره', 'forooshyar'),
                        'reset' => __('بازنشانی', 'forooshyar'),
                        'copy' => __('کپی', 'forooshyar'),
                        'copied' => __('کپی شد!', 'forooshyar'),
                        'invalidJson' => __('فرمت JSON نامعتبر است', 'forooshyar'),
                        'selectAll' => __('انتخاب همه', 'forooshyar'),
                        'deselectAll' => __('لغو انتخاب همه', 'forooshyar'),
                        'noData' => __('داده‌ای یافت نشد', 'forooshyar'),
                        'refresh' => __('بروزرسانی', 'forooshyar')
                    ]
                ]);
                
                // Enqueue WordPress media library and jQuery UI
                wp_enqueue_media();
                wp_enqueue_script('jquery');
                wp_enqueue_script('jquery-ui-tooltip');
                wp_enqueue_script('jquery-ui-datepicker');
                
                // Enqueue WordPress admin styles for consistency
                wp_enqueue_style('wp-admin');
                wp_enqueue_style('dashicons');
                
            } else {
            }
        });
        
        // Register services with proper dependencies
        Container::bind(ConfigService::class, function(){
            return new ConfigService();
        });
        
        Container::bind(CacheService::class, function(){
            return new CacheService(Container::resolve(ConfigService::class));
        });

        Container::bind(LoggingService::class, function(){
            return new LoggingService(Container::resolve(ConfigService::class));
        });

        Container::bind(ErrorHandlingService::class, function(){
            return new ErrorHandlingService(
                Container::resolve(ConfigService::class),
                Container::resolve(CacheService::class),
                Container::resolve(LoggingService::class),
            );
        });
        
        Container::bind(TitleBuilder::class, function(){
            return new TitleBuilder(Container::resolve(ConfigService::class));
        });
        
        Container::bind(ProductService::class, function(){
            return new ProductService(
                Container::resolve(ConfigService::class),
                Container::resolve(TitleBuilder::class)
            );
        });
        
        // Register cache invalidation service
        Container::bind(CacheInvalidationService::class, function(){
            return new CacheInvalidationService(
                Container::resolve(CacheService::class),
                Container::resolve(ErrorHandlingService::class),
                Container::resolve(LoggingService::class),
            );
        });
        
        // Register API logging service
        Container::bind(ApiLogService::class, function(){
            return new ApiLogService(Container::resolve(ConfigService::class));
        });
        
        // Register log cleanup service
        Container::bind(LogCleanupService::class, function(){
            return new LogCleanupService(
                Container::resolve(ConfigService::class),
                Container::resolve(ApiLogService::class)
            );
        });
        
        // Register Persian date service
        Container::bind(\Forooshyar\Services\PersianDateService::class, function(){
            return new \Forooshyar\Services\PersianDateService();
        });
        
        // Initialize cache invalidation hooks
        Container::resolve(CacheInvalidationService::class);
        
        // Initialize log cleanup service
        Container::resolve(LogCleanupService::class)->init();
        
        appLogger('Forooshyar: onInit() completed successfully');
    }
    
    public function boot() {
        
        // Boot AI Agent Module
        if ($this->aiAgentModule !== null) {
            $this->aiAgentModule->boot();
            appLogger('Forooshyar: AI Agent Module booted');
        }
    }
    
    public function ajax() {
    }
    
    public function rest() {
    }
    
    public function activate() {
        appLogger('Forooshyar: activate() method called');
    }
    
    public function deactivate() {
        appLogger('Forooshyar: deactivate() method called');
    }
    
    public function uninstall() {
        appLogger('Forooshyar: uninstall() method called');
    }
}