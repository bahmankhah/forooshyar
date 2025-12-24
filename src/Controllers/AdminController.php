<?php

namespace Forooshyar\Controllers;

use Forooshyar\Services\ConfigService;
use WPLite\Facades\View;

class AdminController extends Controller
{
    /** @var ConfigService */
    private $configService;

    public function __construct()
    {
        $this->configService = new ConfigService();
    }

    /**
     * Display the settings page
     */
    public function settingsPage(): void
    {
        $config = $this->configService->getAll();
        $variables = $this->configService->getAvailableVariables();
        $persianDate = new \Forooshyar\Services\PersianDateService();
        
        View::render('admin.settings', [
            'config' => $config,
            'variables' => $variables,
            'page_title' => __('تنظیمات فروشیار', 'forooshyar'),
            'current_tab' => $_GET['tab'] ?? 'general',
            'persian_date' => $persianDate,
            'current_date_info' => $persianDate->getCalendarInfo()
        ]);
    }

    /**
     * Display the API monitor page
     */
    public function monitorPage(): void
    {
        $persianDate = new \Forooshyar\Services\PersianDateService();
        
        View::render('admin.monitor', [
            'page_title' => __('مانیتور API فروشیار', 'forooshyar'),
            'api_endpoints' => $this->getApiEndpoints(),
            'persian_date' => $persianDate,
            'current_date_info' => $persianDate->getCalendarInfo()
        ]);
    }

    /**
     * Handle settings save via AJAX
     */
    public function saveSettings(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['forooshyar_nonce'] ?? '', 'forooshyar_settings')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            // Get form data and save each section
            $success = true;
            $savedSections = [];
            
            // Save general settings
            if (isset($_POST['general'])) {
                $generalSettings = $this->sanitizeGeneralSettings($_POST['general']);
                $success = $this->configService->set('general', $generalSettings) && $success;
                $savedSections['general'] = $generalSettings;
            }
            
            // Save fields settings
            if (isset($_POST['fields'])) {
                $fieldsSettings = $this->sanitizeFieldsSettings($_POST['fields']);
                $success = $this->configService->set('fields', $fieldsSettings) && $success;
                $savedSections['fields'] = $fieldsSettings;
            }
            
            // Save images settings
            if (isset($_POST['images'])) {
                $imagesSettings = $this->sanitizeImagesSettings($_POST['images']);
                $success = $this->configService->set('images', $imagesSettings) && $success;
                $savedSections['images'] = $imagesSettings;
            }
            
            // Save cache settings
            if (isset($_POST['cache'])) {
                $cacheSettings = $this->sanitizeCacheSettings($_POST['cache']);
                $success = $this->configService->set('cache', $cacheSettings) && $success;
                $savedSections['cache'] = $cacheSettings;
            }
            
            // Save API settings
            if (isset($_POST['api'])) {
                $apiSettings = $this->sanitizeApiSettings($_POST['api']);
                $success = $this->configService->set('api', $apiSettings) && $success;
                $savedSections['api'] = $apiSettings;
            }
            
            // Save advanced settings
            if (isset($_POST['advanced'])) {
                $advancedSettings = $this->sanitizeAdvancedSettings($_POST['advanced']);
                $success = $this->configService->set('advanced', $advancedSettings) && $success;
                $savedSections['advanced'] = $advancedSettings;
            }
            
            if ($success) {
                wp_send_json_success([
                    'message' => __('تنظیمات با موفقیت ذخیره شد', 'forooshyar'),
                    'settings' => $savedSections
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('خطا در ذخیره برخی تنظیمات', 'forooshyar')
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در ذخیره تنظیمات: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle settings reset via AJAX
     */
    public function resetSettings(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_settings')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $this->configService->reset();
            
            wp_send_json_success([
                'message' => __('تنظیمات به حالت پیش‌فرض بازگردانده شد', 'forooshyar'),
                'settings' => $this->configService->getAll()
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در بازگردانی تنظیمات: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Test API endpoint via AJAX
     */
    public function testApi(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_api_test')) {
            wp_die(__('خطای امنیتی', 'forooshyar'));
        }

        $endpoint = sanitize_text_field($_POST['endpoint'] ?? '');
        $params = $_POST['params'] ?? [];

        try {
            $start_time = microtime(true);
            
            // Make internal API call
            $response = $this->makeApiCall($endpoint, $params);
            
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000, 2);

            wp_send_json_success([
                'response' => $response,
                'response_time' => $response_time,
                'cache_status' => $this->getCacheStatus($endpoint, $params)
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در تست API: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get API logs via AJAX (legacy method - redirects to new implementation)
     */
    public function getLogs(): void
    {
        $this->getApiLogs();
    }

    /**
     * Get usage statistics via AJAX
     */
    public function getStatsAjax(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $stats = $this->getStats();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('خطا در دریافت آمار: ', 'forooshyar') . $e->getMessage()]);
        }
    }

    /**
     * Export settings via AJAX
     */
    public function exportSettings(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $exportData = $this->configService->export();
            
            // Set headers for file download
            $filename = 'forooshyar-settings-' . date('Y-m-d-H-i-s') . '.json';
            
            wp_send_json_success([
                'filename' => $filename,
                'data' => $exportData,
                'message' => __('تنظیمات با موفقیت صادر شد', 'forooshyar')
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در صادرات تنظیمات: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Import settings via AJAX
     */
    public function importSettings(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_settings')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            // Check if file was uploaded
            if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => __('فایل تنظیمات آپلود نشد', 'forooshyar')]);
                return;
            }

            // Read and validate file content
            $fileContent = file_get_contents($_FILES['settings_file']['tmp_name']);
            $importData = json_decode($fileContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => __('فایل تنظیمات نامعتبر است', 'forooshyar')]);
                return;
            }

            // Validate import data structure
            if (!isset($importData['config']) || !is_array($importData['config'])) {
                wp_send_json_error(['message' => __('ساختار فایل تنظیمات نامعتبر است', 'forooshyar')]);
                return;
            }

            // Import settings
            $success = $this->configService->import($importData);

            if ($success) {
                wp_send_json_success([
                    'message' => __('تنظیمات با موفقیت وارد شد', 'forooshyar'),
                    'settings' => $this->configService->getAll()
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('خطا در وارد کردن برخی تنظیمات', 'forooshyar')
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در وارد کردن تنظیمات: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get usage statistics (updated to use ApiLogService)
     */
    public function getStats(): array
    {
        try {
            // Get API log service
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            
            // Get cache statistics
            $cacheService = new \Forooshyar\Services\CacheService($this->configService);
            $cacheStats = $cacheService->getStats();
            
            // Get performance metrics from log service
            $performanceMetrics = $logService->getPerformanceMetrics();
            
            // Get analytics for last 24 hours
            $analytics = $logService->getAnalytics([
                'start_date' => date('Y-m-d H:i:s', strtotime('-24 hours'))
            ]);
            
            // Get basic WordPress statistics
            $totalProducts = wp_count_posts('product');
            $totalVariations = wp_count_posts('product_variation');
            
            // Calculate some basic metrics
            $publishedProducts = $totalProducts->publish ?? 0;
            $publishedVariations = $totalVariations->publish ?? 0;
            
            // Use cache hit rate from API logs, fallback to cache stats option
            $cacheHitRate = $performanceMetrics['cache_hit_rate_24h'];
            if ($cacheHitRate == 0 && isset($cacheStats['hit_rate']) && $cacheStats['hit_rate'] > 0) {
                $cacheHitRate = $cacheStats['hit_rate'];
            }
            
            return [
                'total_requests' => $performanceMetrics['requests_last_24h'],
                'cache_hit_rate' => $cacheHitRate,
                'average_response_time' => $performanceMetrics['avg_response_time_24h'],
                'today_requests' => $performanceMetrics['requests_last_24h'],
                'total_products' => $publishedProducts,
                'total_variations' => $publishedVariations,
                'cache_entries' => $cacheStats['total_entries'],
                'cache_enabled' => $cacheStats['enabled'],
                'cache_hits' => $cacheStats['hits'] ?? 0,
                'cache_misses' => $cacheStats['misses'] ?? 0,
                'top_endpoints' => $analytics['top_endpoints'] ?? [],
                'error_rate' => $performanceMetrics['error_rate_24h'],
                'max_response_time' => $performanceMetrics['max_response_time_24h'],
                'rate_limit_usage' => $performanceMetrics['current_rate_limit_usage'],
                'cleanup_needed' => $performanceMetrics['cleanup_needed']
            ];
            
        } catch (\Exception $e) {
            error_log('خطا در دریافت آمار: ' . $e->getMessage());
            
            return [
                'total_requests' => 0,
                'cache_hit_rate' => 0,
                'average_response_time' => 0,
                'today_requests' => 0,
                'total_products' => 0,
                'total_variations' => 0,
                'cache_entries' => 0,
                'cache_enabled' => false,
                'top_endpoints' => [],
                'error_rate' => 0,
                'max_response_time' => 0,
                'rate_limit_usage' => 0,
                'cleanup_needed' => false
            ];
        }
    }

    /**
     * Register WordPress admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('فروشیار', 'forooshyar'),           // Page title
            __('فروشیار', 'forooshyar'),           // Menu title
            'manage_options',                        // Capability
            'forooshyar',                           // Menu slug
            [$this, 'settingsPage'],               // Callback
            'dashicons-store',                      // Icon
            20                                      // Position
        );

        add_submenu_page(
            'forooshyar',                          // Parent slug
            __('تنظیمات فروشیار', 'forooshyar'),    // Page title
            __('تنظیمات', 'forooshyar'),           // Menu title
            'manage_options',                       // Capability
            'forooshyar',                          // Menu slug (same as parent for first item)
            [$this, 'settingsPage']               // Callback
        );

        add_submenu_page(
            'forooshyar',                          // Parent slug
            __('مانیتور API فروشیار', 'forooshyar'), // Page title
            __('مانیتور API', 'forooshyar'),        // Menu title
            'manage_options',                       // Capability
            'forooshyar-monitor',                  // Menu slug
            [$this, 'monitorPage']                 // Callback
        );
    }

    /**
     * Get available API endpoints for testing
     */
    private function getApiEndpoints(): array
    {
        return [
            'products' => [
                'url' => rest_url('forooshyar/v1/products'),
                'method' => 'GET',
                'params' => ['page', 'limit', 'show_variations']
            ],
            'product_by_id' => [
                'url' => rest_url('forooshyar/v1/products/{id}'),
                'method' => 'GET',
                'params' => ['id']
            ],
            'products_by_ids' => [
                'url' => rest_url('forooshyar/v1/products/by-ids'),
                'method' => 'POST',
                'params' => ['ids']
            ],
            'products_by_slugs' => [
                'url' => rest_url('forooshyar/v1/products/by-slugs'),
                'method' => 'POST',
                'params' => ['slugs']
            ]
        ];
    }

    /**
     * Make internal API call for testing
     */
    private function makeApiCall(string $endpoint, array $params): array
    {
        try {
            $productController = new \Forooshyar\Controllers\ProductController();
            
            switch ($endpoint) {
                case 'products':
                    $request = new \WP_REST_Request('GET', '/forooshyar/v1/products');
                    foreach ($params as $key => $value) {
                        $request->set_param($key, $value);
                    }
                    $response = $productController->index($request);
                    break;
                    
                case 'product_by_id':
                    if (empty($params['id'])) {
                        throw new \Exception('شناسه محصول الزامی است');
                    }
                    $request = new \WP_REST_Request('GET', '/forooshyar/v1/products/' . $params['id']);
                    $request->set_param('id', $params['id']);
                    $response = $productController->show($request);
                    break;
                    
                case 'products_by_ids':
                    if (empty($params['ids'])) {
                        throw new \Exception('آرایه شناسه‌ها الزامی است');
                    }
                    $request = new \WP_REST_Request('POST', '/forooshyar/v1/products/by-ids');
                    $request->set_param('ids', is_string($params['ids']) ? explode(',', $params['ids']) : $params['ids']);
                    $response = $productController->getByIds($request);
                    break;
                    
                case 'products_by_slugs':
                    if (empty($params['slugs'])) {
                        throw new \Exception('آرایه نام‌ها الزامی است');
                    }
                    $request = new \WP_REST_Request('POST', '/forooshyar/v1/products/by-slugs');
                    $request->set_param('slugs', is_string($params['slugs']) ? explode(',', $params['slugs']) : $params['slugs']);
                    $response = $productController->getBySlugs($request);
                    break;
                    
                default:
                    throw new \Exception('نقطه پایانی نامعتبر');
            }
            
            return $response->get_data();
            
        } catch (\Exception $e) {
            throw new \Exception('خطا در تست API: ' . $e->getMessage());
        }
    }

    /**
     * Get cache status for endpoint
     */
    private function getCacheStatus(string $endpoint, array $params): string
    {
        try {
            $cacheService = new \Forooshyar\Services\CacheService($this->configService);
            
            // Generate the same cache key that would be used for this request
            $cacheKey = $cacheService->generateKey($endpoint, $params);
            
            // Check if data exists in cache
            $cachedData = $cacheService->get($cacheKey);
            
            return $cachedData !== false ? 'hit' : 'miss';
            
        } catch (\Exception $e) {
            return 'error';
        }
    }



    /**
     * Sanitize general settings
     */
    private function sanitizeGeneralSettings(array $settings): array
    {
        return [
            'show_variations' => !empty($settings['show_variations']),
            'title_template' => sanitize_text_field($settings['title_template'] ?? '{{product_name}}{{variation_suffix}}'),
            'custom_suffix' => sanitize_text_field($settings['custom_suffix'] ?? ''),
            'language' => sanitize_text_field($settings['language'] ?? 'fa_IR'),
            'api_version' => sanitize_text_field($settings['api_version'] ?? 'v1'),
            'debug_mode' => !empty($settings['debug_mode'])
        ];
    }

    /**
     * Sanitize fields settings
     */
    private function sanitizeFieldsSettings(array $settings): array
    {
        $sanitized = [];
        
        // Standard fields
        $standardFields = ['title', 'subtitle', 'parent_id', 'page_unique', 'current_price', 'old_price', 
                          'availability', 'category_name', 'image_links', 'image_link', 'page_url', 
                          'short_desc', 'spec', 'date', 'registry', 'guarantee'];
        
        foreach ($standardFields as $field) {
            $sanitized[$field] = !empty($settings[$field]);
        }
        
        // Custom fields and mapping
        $sanitized['custom_fields'] = sanitize_textarea_field($settings['custom_fields'] ?? '');
        $sanitized['field_mapping'] = sanitize_textarea_field($settings['field_mapping'] ?? '');
        
        return $sanitized;
    }

    /**
     * Sanitize images settings
     */
    private function sanitizeImagesSettings(array $settings): array
    {
        $sanitized = [
            'sizes' => [],
            'max_images' => intval($settings['max_images'] ?? 10),
            'quality' => intval($settings['quality'] ?? 80),
            'format' => sanitize_text_field($settings['format'] ?? 'original'),
            'lazy_loading' => !empty($settings['lazy_loading']),
            'placeholder' => esc_url_raw($settings['placeholder'] ?? ''),
            'cdn_url' => esc_url_raw($settings['cdn_url'] ?? ''),
            'watermark' => [
                'enabled' => !empty($settings['watermark']['enabled']),
                'image' => esc_url_raw($settings['watermark']['image'] ?? ''),
                'position' => sanitize_text_field($settings['watermark']['position'] ?? 'bottom-right'),
                'opacity' => intval($settings['watermark']['opacity'] ?? 50)
            ]
        ];
        
        // Sanitize image sizes
        $allowedSizes = ['thumbnail', 'medium', 'large', 'full'];
        if (isset($settings['sizes']) && is_array($settings['sizes'])) {
            foreach ($settings['sizes'] as $size) {
                if (in_array($size, $allowedSizes)) {
                    $sanitized['sizes'][] = $size;
                }
            }
        }
        
        // Ensure at least one size is selected
        if (empty($sanitized['sizes'])) {
            $sanitized['sizes'] = ['medium'];
        }
        
        return $sanitized;
    }

    /**
     * Sanitize cache settings
     */
    private function sanitizeCacheSettings(array $settings): array
    {
        $sanitized = [
            'enabled' => !empty($settings['enabled']),
            'ttl' => max(60, intval($settings['ttl'] ?? 3600)),
            'auto_invalidate' => !empty($settings['auto_invalidate']),
            'storage' => sanitize_text_field($settings['storage'] ?? 'transient'),
            'compression' => !empty($settings['compression']),
            'prefix' => sanitize_text_field($settings['prefix'] ?? 'forooshyar_'),
            'invalidation_events' => [],
            'warmup' => !empty($settings['warmup'])
        ];
        
        // Sanitize invalidation events
        $allowedEvents = ['product_save', 'product_delete', 'variation_save', 'category_update', 'stock_change'];
        if (isset($settings['invalidation_events']) && is_array($settings['invalidation_events'])) {
            foreach ($settings['invalidation_events'] as $event) {
                if (in_array($event, $allowedEvents)) {
                    $sanitized['invalidation_events'][] = $event;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize API settings
     */
    private function sanitizeApiSettings(array $settings): array
    {
        $sanitized = [
            'max_per_page' => max(1, min(1000, intval($settings['max_per_page'] ?? 100))),
            'timeout' => max(5, min(300, intval($settings['timeout'] ?? 30))),
            'require_user_agent' => !empty($settings['require_user_agent']),
            'allowed_user_agents' => sanitize_textarea_field($settings['allowed_user_agents'] ?? ''),
            'ip_whitelist' => sanitize_textarea_field($settings['ip_whitelist'] ?? ''),
            'ip_blacklist' => sanitize_textarea_field($settings['ip_blacklist'] ?? ''),
            'compression' => !empty($settings['compression']),
            'rate_limit' => [
                'enabled' => !empty($settings['rate_limit']['enabled']),
                'per_minute' => max(1, intval($settings['rate_limit']['per_minute'] ?? 60)),
                'per_hour' => max(1, intval($settings['rate_limit']['per_hour'] ?? 1000)),
                'per_day' => max(1, intval($settings['rate_limit']['per_day'] ?? 10000))
            ],
            'cors' => [
                'enabled' => !empty($settings['cors']['enabled']),
                'origins' => sanitize_text_field($settings['cors']['origins'] ?? '*'),
                'methods' => sanitize_text_field($settings['cors']['methods'] ?? 'GET,POST,OPTIONS'),
                'headers' => sanitize_text_field($settings['cors']['headers'] ?? 'Content-Type,Authorization')
            ]
        ];
        
        return $sanitized;
    }

    /**
     * Sanitize advanced settings
     */
    private function sanitizeAdvancedSettings(array $settings): array
    {
        $sanitized = [
            'custom_field_mapping' => sanitize_textarea_field($settings['custom_field_mapping'] ?? ''),
            'memory_limit' => max(128, min(2048, intval($settings['memory_limit'] ?? 256))),
            'execution_time_limit' => max(30, min(300, intval($settings['execution_time_limit'] ?? 60))),
            'error_reporting' => sanitize_text_field($settings['error_reporting'] ?? 'basic'),
            'log_retention' => max(1, min(365, intval($settings['log_retention'] ?? 30))),
            'api_hooks' => sanitize_textarea_field($settings['api_hooks'] ?? ''),
            'response_filters' => sanitize_textarea_field($settings['response_filters'] ?? ''),
            'db_optimization' => [
                'use_indexes' => !empty($settings['db_optimization']['use_indexes']),
                'limit_queries' => !empty($settings['db_optimization']['limit_queries']),
                'use_prepared_statements' => !empty($settings['db_optimization']['use_prepared_statements']),
                'batch_processing' => !empty($settings['db_optimization']['batch_processing'])
            ],
            'circuit_breaker' => [
                'enabled' => !empty($settings['circuit_breaker']['enabled']),
                'failure_threshold' => max(1, intval($settings['circuit_breaker']['failure_threshold'] ?? 5)),
                'recovery_timeout' => max(10, intval($settings['circuit_breaker']['recovery_timeout'] ?? 60))
            ]
        ];
        
        return $sanitized;
    }



    /**
     * Validate template syntax via AJAX
     */
    public function validateTemplate(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_settings')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $template = sanitize_text_field($_POST['template'] ?? '');
            
            if (empty($template)) {
                wp_send_json_error(['message' => __('الگوی عنوان نمی‌تواند خالی باشد', 'forooshyar')]);
                return;
            }

            $validation = $this->configService->validateTitleTemplate($template);

            if ($validation['valid']) {
                wp_send_json_success([
                    'message' => __('الگوی عنوان معتبر است', 'forooshyar'),
                    'template' => $template
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('الگوی عنوان نامعتبر است', 'forooshyar'),
                    'errors' => $validation['errors']
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در اعتبارسنجی الگو: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get API analytics via AJAX
     */
    public function getAnalytics(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            
            // Get filters from request
            $filters = [
                'start_date' => sanitize_text_field($_GET['start_date'] ?? ''),
                'end_date' => sanitize_text_field($_GET['end_date'] ?? ''),
                'endpoint' => sanitize_text_field($_GET['endpoint'] ?? '')
            ];
            
            // Remove empty filters
            $filters = array_filter($filters);
            
            $analytics = $logService->getAnalytics($filters);
            
            wp_send_json_success($analytics);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در دریافت آمار: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get API logs via AJAX (updated to use ApiLogService)
     */
    public function getApiLogs(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            
            $page = intval($_GET['page'] ?? 1);
            $perPage = intval($_GET['per_page'] ?? 50);
            
            // Get filters from request
            $filters = [
                'ip' => sanitize_text_field($_GET['ip'] ?? ''),
                'endpoint' => sanitize_text_field($_GET['endpoint'] ?? ''),
                'status_code' => intval($_GET['status_code'] ?? 0) ?: null,
                'start_date' => sanitize_text_field($_GET['start_date'] ?? ''),
                'end_date' => sanitize_text_field($_GET['end_date'] ?? '')
            ];
            
            // Remove empty filters
            $filters = array_filter($filters);
            
            $logs = $logService->getLogs($page, $perPage, $filters);
            
            wp_send_json_success($logs);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در دریافت لاگ‌ها: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get performance metrics via AJAX
     */
    public function getPerformanceMetrics(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            $metrics = $logService->getPerformanceMetrics();
            
            wp_send_json_success($metrics);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در دریافت معیارهای عملکرد: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup old logs via AJAX
     */
    public function cleanupLogs(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_cleanup')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            
            $daysToKeep = intval($_POST['days_to_keep'] ?? 30);
            $daysToKeep = max(1, min(365, $daysToKeep)); // Between 1 and 365 days
            
            $result = $logService->cleanupLogs($daysToKeep);
            
            wp_send_json_success([
                'message' => sprintf(
                    __('%d لاگ و %d رکورد محدودیت نرخ حذف شد', 'forooshyar'),
                    $result['logs_deleted'],
                    $result['rate_limits_deleted']
                ),
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در پاکسازی لاگ‌ها: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Check rate limit status for IP via AJAX
     */
    public function checkRateLimit(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            
            $ip = sanitize_text_field($_GET['ip'] ?? '');
            if (empty($ip)) {
                wp_send_json_error(['message' => __('آدرس IP الزامی است', 'forooshyar')]);
                return;
            }
            
            // Temporarily set the IP for checking
            $_SERVER['REMOTE_ADDR'] = $ip;
            
            $rateLimitInfo = $logService->checkRateLimit();
            
            wp_send_json_success($rateLimitInfo);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در بررسی محدودیت نرخ: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get error logs via AJAX
     */
    public function getErrorLogs(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $loggingService = new \Forooshyar\Services\LoggingService($this->configService);
            
            $page = intval($_GET['page'] ?? 1);
            $perPage = intval($_GET['per_page'] ?? 50);
            
            // Get filters from request
            $filters = [
                'category' => sanitize_text_field($_GET['category'] ?? ''),
                'operation' => sanitize_text_field($_GET['operation'] ?? ''),
                'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
                'date_to' => sanitize_text_field($_GET['date_to'] ?? '')
            ];
            
            // Remove empty filters
            $filters = array_filter($filters);
            
            $logs = $loggingService->getErrorLogs($filters, $page, $perPage);
            
            wp_send_json_success($logs);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در دریافت لاگ خطاها: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get error statistics via AJAX
     */
    public function getErrorStats(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $loggingService = new \Forooshyar\Services\LoggingService($this->configService);
            
            $days = intval($_GET['days'] ?? 7);
            $days = max(1, min(365, $days)); // Between 1 and 365 days
            
            $stats = $loggingService->getErrorStatistics($days);
            
            wp_send_json_success($stats);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در دریافت آمار خطاها: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Clear error logs via AJAX
     */
    public function clearErrorLogs(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_clear_logs')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $loggingService = new \Forooshyar\Services\LoggingService($this->configService);
            
            // Get filters from request
            $filters = [
                'category' => sanitize_text_field($_POST['category'] ?? ''),
                'older_than_days' => intval($_POST['older_than_days'] ?? 0) ?: null
            ];
            
            // Remove empty filters
            $filters = array_filter($filters);
            
            $deletedCount = $loggingService->clearLogs($filters);
            
            wp_send_json_success([
                'message' => sprintf(
                    __('%d رکورد خطا حذف شد', 'forooshyar'),
                    $deletedCount
                ),
                'deleted_count' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در پاکسازی لاگ خطاها: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get circuit breaker statistics via AJAX
     */
    public function getCircuitBreakerStats(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $configService = new \Forooshyar\Services\ConfigService();
            $cacheService = new \Forooshyar\Services\CacheService($configService);
            $loggingService = new \Forooshyar\Services\LoggingService($configService);
            $errorHandlingService = new \Forooshyar\Services\ErrorHandlingService($configService, $cacheService, $loggingService);
            
            $stats = $errorHandlingService->getCircuitBreakerStats();
            
            wp_send_json_success($stats);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در دریافت آمار محافظ مدار: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Reset circuit breaker via AJAX
     */
    public function resetCircuitBreaker(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_reset_circuit')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $operation = sanitize_text_field($_POST['operation'] ?? '');
            
            if (empty($operation)) {
                wp_send_json_error(['message' => __('نام عملیات الزامی است', 'forooshyar')]);
                return;
            }
            
            // Delete circuit breaker data for the operation
            $key = "circuit_breaker_{$operation}";
            delete_transient($key);
            
            wp_send_json_success([
                'message' => sprintf(
                    __('محافظ مدار برای عملیات %s بازنشانی شد', 'forooshyar'),
                    $operation
                )
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در بازنشانی محافظ مدار: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Export error logs via AJAX
     */
    public function exportErrorLogs(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $loggingService = new \Forooshyar\Services\LoggingService($this->configService);
            
            // Get filters from request
            $filters = [
                'category' => sanitize_text_field($_GET['category'] ?? ''),
                'operation' => sanitize_text_field($_GET['operation'] ?? ''),
                'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
                'date_to' => sanitize_text_field($_GET['date_to'] ?? '')
            ];
            
            // Remove empty filters
            $filters = array_filter($filters);
            
            $csv = $loggingService->exportLogs($filters);
            
            // Set headers for CSV download
            $filename = 'forooshyar-error-logs-' . date('Y-m-d-H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            // Add BOM for proper UTF-8 encoding in Excel
            echo "\xEF\xBB\xBF";
            echo $csv;
            
            exit;
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در صادرات لاگ خطاها: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Export all settings (complete backup) via AJAX
     */
    public function exportAllSettings(): void
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            // Get complete configuration backup
            $completeBackup = $this->createCompleteBackup();
            
            wp_send_json_success([
                'filename' => 'forooshyar-complete-backup-' . date('Y-m-d-H-i-s') . '.json',
                'data' => $completeBackup,
                'message' => __('پشتیبان کامل تنظیمات با موفقیت ایجاد شد', 'forooshyar')
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در ایجاد پشتیبان کامل: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Import all settings (complete restore) via AJAX
     */
    public function importAllSettings(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_advanced')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $settingsJson = sanitize_textarea_field($_POST['settings'] ?? '');
            
            if (empty($settingsJson)) {
                wp_send_json_error(['message' => __('داده‌های تنظیمات ارسال نشده است', 'forooshyar')]);
                return;
            }

            $backupData = json_decode($settingsJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => __('فرمت فایل پشتیبان نامعتبر است', 'forooshyar')]);
                return;
            }

            // Validate backup structure
            $validation = $this->validateBackupData($backupData);
            if (!$validation['valid']) {
                wp_send_json_error([
                    'message' => __('ساختار فایل پشتیبان نامعتبر است: ', 'forooshyar') . implode(', ', $validation['errors'])
                ]);
                return;
            }

            // Restore from complete backup
            $success = $this->restoreFromCompleteBackup($backupData);

            if ($success) {
                wp_send_json_success([
                    'message' => __('تنظیمات با موفقیت از پشتیبان بازیابی شد', 'forooshyar'),
                    'settings' => $this->configService->getAll()
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('خطا در بازیابی برخی تنظیمات از پشتیبان', 'forooshyar')
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در بازیابی تنظیمات: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Performance test via AJAX
     */
    public function performanceTest(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_advanced')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $results = $this->runPerformanceTest();
            
            wp_send_json_success($results);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در اجرای تست عملکرد: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup operations via AJAX
     */
    public function cleanup(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'forooshyar_advanced')) {
            wp_send_json_error(['message' => __('خطای امنیتی', 'forooshyar')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('شما مجوز دسترسی به این بخش را ندارید', 'forooshyar')]);
            return;
        }

        try {
            $cleanupType = sanitize_text_field($_POST['cleanup_type'] ?? '');
            
            switch ($cleanupType) {
                case 'logs':
                    $result = $this->cleanupOldLogs();
                    break;
                case 'cache':
                    $result = $this->cleanupExpiredCache();
                    break;
                case 'database':
                    $result = $this->optimizeDatabase();
                    break;
                default:
                    throw new \Exception(__('نوع پاکسازی نامعتبر است', 'forooshyar'));
            }
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('خطا در عملیات پاکسازی: ', 'forooshyar') . $e->getMessage()
            ]);
        }
    }

    /**
     * Create complete backup of all settings
     */
    private function createCompleteBackup(): array
    {
        return [
            'backup_info' => [
                'version' => '2.0.0',
                'created_at' => current_time('mysql'),
                'site_url' => get_site_url(),
                'wp_version' => get_bloginfo('version'),
                'plugin_version' => '2.0.0'
            ],
            'config' => $this->configService->getAll(),
            'template_variables' => $this->configService->getAvailableVariables(),
            'wordpress_options' => $this->getRelevantWordPressOptions(),
            'database_info' => $this->getDatabaseInfo()
        ];
    }

    /**
     * Validate backup data structure
     */
    private function validateBackupData(array $data): array
    {
        $errors = [];
        
        // Check required top-level keys
        $requiredKeys = ['backup_info', 'config'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                $errors[] = sprintf(__('کلید الزامی %s موجود نیست', 'forooshyar'), $key);
            }
        }
        
        // Validate backup info
        if (isset($data['backup_info'])) {
            if (!isset($data['backup_info']['version'])) {
                $errors[] = __('اطلاعات نسخه در پشتیبان موجود نیست', 'forooshyar');
            }
        }
        
        // Validate config structure
        if (isset($data['config'])) {
            $expectedSections = ['general', 'fields', 'images', 'cache', 'api', 'advanced'];
            foreach ($expectedSections as $section) {
                if (!isset($data['config'][$section])) {
                    $errors[] = sprintf(__('بخش تنظیمات %s موجود نیست', 'forooshyar'), $section);
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Restore from complete backup
     */
    private function restoreFromCompleteBackup(array $backupData): bool
    {
        $success = true;
        
        try {
            // Restore main configuration
            if (isset($backupData['config'])) {
                foreach ($backupData['config'] as $section => $sectionData) {
                    if (!$this->configService->set($section, $sectionData)) {
                        $success = false;
                    }
                }
            }
            
            // Log the restore operation
            $this->logRestoreOperation($backupData);
            
        } catch (\Exception $e) {
            error_log('خطا در بازیابی پشتیبان: ' . $e->getMessage());
            $success = false;
        }
        
        return $success;
    }

    /**
     * Get relevant WordPress options for backup
     */
    private function getRelevantWordPressOptions(): array
    {
        $options = [];
        
        // Get all forooshyar-related options
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'forooshyar_%'
            )
        );
        
        foreach ($results as $row) {
            $options[$row->option_name] = maybe_unserialize($row->option_value);
        }
        
        return $options;
    }

    /**
     * Get database information for backup
     */
    private function getDatabaseInfo(): array
    {
        global $wpdb;
        
        return [
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'prefix' => $wpdb->prefix,
            'version' => $wpdb->db_version()
        ];
    }

    /**
     * Run performance test
     */
    private function runPerformanceTest(): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Test API endpoints
        $testResults = [];
        
        try {
            // Test products endpoint
            $productController = new \Forooshyar\Controllers\ProductController();
            $request = new \WP_REST_Request('GET', '/forooshyar/v1/products');
            $request->set_param('per_page', 10);
            
            $testStart = microtime(true);
            $response = $productController->index($request);
            $testEnd = microtime(true);
            
            $testResults['products_endpoint'] = [
                'response_time' => round(($testEnd - $testStart) * 1000, 2),
                'success' => $response->get_status() === 200,
                'data_count' => count($response->get_data()['products'] ?? [])
            ];
            
        } catch (\Exception $e) {
            $testResults['products_endpoint'] = [
                'response_time' => 0,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        return [
            'total_time' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage' => round(($endMemory - $startMemory) / 1024 / 1024, 2),
            'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            'avg_response_time' => $testResults['products_endpoint']['response_time'] ?? 0,
            'max_response_time' => $testResults['products_endpoint']['response_time'] ?? 0,
            'query_count' => get_num_queries(),
            'test_results' => $testResults
        ];
    }

    /**
     * Cleanup old logs
     */
    private function cleanupOldLogs(): array
    {
        try {
            $logService = new \Forooshyar\Services\ApiLogService($this->configService);
            $retentionDays = $this->configService->get('advanced')['log_retention'] ?? 30;
            
            $result = $logService->cleanupLogs($retentionDays);
            
            return [
                'message' => sprintf(
                    __('%d لاگ قدیمی پاک شد', 'forooshyar'),
                    $result['logs_deleted']
                ),
                'logs_deleted' => $result['logs_deleted'],
                'space_freed' => $result['space_freed'] ?? 0
            ];
            
        } catch (\Exception $e) {
            throw new \Exception(__('خطا در پاکسازی لاگ‌ها: ', 'forooshyar') . $e->getMessage());
        }
    }

    /**
     * Cleanup expired cache
     */
    private function cleanupExpiredCache(): array
    {
        try {
            $cacheService = new \Forooshyar\Services\CacheService($this->configService);
            
            // Get cache statistics before cleanup
            $statsBefore = $cacheService->getStats();
            
            // Perform cleanup
            $cleaned = $cacheService->cleanupExpired();
            
            // Get statistics after cleanup
            $statsAfter = $cacheService->getStats();
            
            return [
                'message' => sprintf(
                    __('%d ورودی کش منقضی پاک شد', 'forooshyar'),
                    $cleaned
                ),
                'entries_cleaned' => $cleaned,
                'entries_before' => $statsBefore['total_entries'] ?? 0,
                'entries_after' => $statsAfter['total_entries'] ?? 0
            ];
            
        } catch (\Exception $e) {
            throw new \Exception(__('خطا در پاکسازی کش: ', 'forooshyar') . $e->getMessage());
        }
    }

    /**
     * Optimize database
     */
    private function optimizeDatabase(): array
    {
        try {
            global $wpdb;
            
            $optimizedTables = [];
            $totalSpaceFreed = 0;
            
            // Get forooshyar-related tables
            $tables = [
                $wpdb->prefix . 'forooshyar_api_logs',
                $wpdb->prefix . 'forooshyar_rate_limits',
                $wpdb->prefix . 'forooshyar_error_logs'
            ];
            
            foreach ($tables as $table) {
                // Check if table exists
                $tableExists = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table
                ));
                
                if ($tableExists) {
                    // Get table size before optimization
                    $sizeBefore = $wpdb->get_var($wpdb->prepare(
                        "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size' 
                         FROM information_schema.TABLES 
                         WHERE table_schema = %s AND table_name = %s",
                        DB_NAME,
                        $table
                    ));
                    
                    // Optimize table
                    $wpdb->query("OPTIMIZE TABLE `{$table}`");
                    
                    // Get table size after optimization
                    $sizeAfter = $wpdb->get_var($wpdb->prepare(
                        "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size' 
                         FROM information_schema.TABLES 
                         WHERE table_schema = %s AND table_name = %s",
                        DB_NAME,
                        $table
                    ));
                    
                    $spaceFreed = max(0, $sizeBefore - $sizeAfter);
                    $totalSpaceFreed += $spaceFreed;
                    
                    $optimizedTables[] = [
                        'table' => $table,
                        'size_before' => $sizeBefore,
                        'size_after' => $sizeAfter,
                        'space_freed' => $spaceFreed
                    ];
                }
            }
            
            return [
                'message' => sprintf(
                    __('%d جدول بهینه‌سازی شد، %s مگابایت فضا آزاد شد', 'forooshyar'),
                    count($optimizedTables),
                    number_format($totalSpaceFreed, 2)
                ),
                'optimized_tables' => $optimizedTables,
                'total_space_freed' => $totalSpaceFreed
            ];
            
        } catch (\Exception $e) {
            throw new \Exception(__('خطا در بهینه‌سازی پایگاه داده: ', 'forooshyar') . $e->getMessage());
        }
    }

    /**
     * Log restore operation
     */
    private function logRestoreOperation(array $backupData): void
    {
        try {
            $loggingService = new \Forooshyar\Services\LoggingService($this->configService);
            
            $loggingService->logOperation('settings_restore', [
                'backup_version' => $backupData['backup_info']['version'] ?? 'unknown',
                'backup_created_at' => $backupData['backup_info']['created_at'] ?? 'unknown',
                'restored_at' => current_time('mysql'),
                'restored_by' => get_current_user_id()
            ]);
            
        } catch (\Exception $e) {
            error_log('خطا در ثبت لاگ بازیابی: ' . $e->getMessage());
        }
    }
}