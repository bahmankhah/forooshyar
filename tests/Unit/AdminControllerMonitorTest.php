<?php

use Forooshyar\Controllers\AdminController;
use Forooshyar\Services\ConfigService;

beforeEach(function () {
    // Mock WordPress functions
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action) {
            return true;
        }
    }
    
    if (!function_exists('current_user_can')) {
        function current_user_can($capability) {
            return true;
        }
    }
    
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data) {
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }
    }
    
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data) {
            echo json_encode(['success' => false, 'data' => $data]);
            exit;
        }
    }
    
    if (!function_exists('rest_url')) {
        function rest_url($path) {
            return 'http://example.com/wp-json/' . $path;
        }
    }
    
    if (!function_exists('__')) {
        function __($text, $domain = 'default') {
            return $text;
        }
    }
    
    if (!function_exists('_e')) {
        function _e($text, $domain = 'default') {
            echo $text;
        }
    }
    
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            return $default;
        }
    }
    
    if (!function_exists('wp_count_posts')) {
        function wp_count_posts($type) {
            return (object) ['publish' => 10];
        }
    }
    
    // Mock global $wpdb
    global $wpdb;
    if (!$wpdb) {
        $wpdb = new class {
            public $options = 'wp_options';
            
            public function get_var($query) {
                return 5; // Mock cache entry count
            }
            
            public function prepare($query, ...$args) {
                return $query;
            }
        };
    }
});

it('can get API endpoints for monitor page', function () {
    $controller = new AdminController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getApiEndpoints');
    $method->setAccessible(true);
    
    $endpoints = $method->invoke($controller);
    
    expect($endpoints)->toBeArray();
    expect($endpoints)->toHaveKey('products');
    expect($endpoints)->toHaveKey('product_by_id');
    expect($endpoints)->toHaveKey('products_by_ids');
    expect($endpoints)->toHaveKey('products_by_slugs');
    
    // Verify endpoint structure
    expect($endpoints['products'])->toHaveKey('url');
    expect($endpoints['products'])->toHaveKey('method');
    expect($endpoints['products'])->toHaveKey('params');
    
    expect($endpoints['products']['method'])->toBe('GET');
    expect($endpoints['products']['params'])->toContain('page');
    expect($endpoints['products']['params'])->toContain('per_page');
});

it('can get usage statistics', function () {
    $controller = new AdminController();
    
    $stats = $controller->getStats();
    
    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('total_requests');
    expect($stats)->toHaveKey('cache_hit_rate');
    expect($stats)->toHaveKey('average_response_time');
    expect($stats)->toHaveKey('today_requests');
    expect($stats)->toHaveKey('total_products');
    expect($stats)->toHaveKey('cache_entries');
    expect($stats)->toHaveKey('cache_enabled');
    
    // Verify data types
    expect($stats['total_requests'])->toBeInt();
    expect($stats['cache_hit_rate'])->toBeFloat();
    expect($stats['average_response_time'])->toBeFloat();
    expect($stats['today_requests'])->toBeInt();
});

it('can generate sample logs for monitor page', function () {
    $controller = new AdminController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('generateSampleLogs');
    $method->setAccessible(true);
    
    $logs = $method->invoke($controller);
    
    expect($logs)->toBeArray();
    expect(count($logs))->toBe(50);
    
    // Verify log structure
    if (!empty($logs)) {
        $log = $logs[0];
        expect($log)->toHaveKey('timestamp');
        expect($log)->toHaveKey('endpoint');
        expect($log)->toHaveKey('ip');
        expect($log)->toHaveKey('response_time');
        expect($log)->toHaveKey('status');
        expect($log)->toHaveKey('cache_status');
    }
});

it('can get paginated API logs', function () {
    $controller = new AdminController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getApiLogs');
    $method->setAccessible(true);
    
    $result = $method->invoke($controller, 1, 10);
    
    expect($result)->toBeArray();
    expect($result)->toHaveKey('logs');
    expect($result)->toHaveKey('total');
    expect($result)->toHaveKey('page');
    expect($result)->toHaveKey('per_page');
    expect($result)->toHaveKey('total_pages');
    
    expect($result['page'])->toBe(1);
    expect($result['per_page'])->toBe(10);
    expect(count($result['logs']))->toBeLessThanOrEqual(10);
});

it('can get cache status for endpoints', function () {
    $controller = new AdminController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getCacheStatus');
    $method->setAccessible(true);
    
    $status = $method->invoke($controller, 'products', ['page' => 1]);
    
    expect($status)->toBeString();
    expect($status)->toBeIn(['hit', 'miss', 'error']);
});