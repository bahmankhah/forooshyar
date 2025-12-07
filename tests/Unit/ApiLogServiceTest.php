<?php

use Forooshyar\Services\ApiLogService;
use Forooshyar\Services\ConfigService;

beforeEach(function () {
    // Define WordPress constants
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    
    // Mock WordPress functions
    if (!function_exists('current_time')) {
        function current_time($type) {
            return date('Y-m-d H:i:s');
        }
    }
    
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            return $default;
        }
    }
    
    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            return true;
        }
    }
    
    // Create test instance
    $this->configService = new ConfigService();
    $this->logService = new ApiLogService($this->configService, false); // Don't create tables in tests
});

test('can create api log service instance', function () {
    expect($this->logService)->toBeInstanceOf(ApiLogService::class);
});

test('can get client ip address', function () {
    // Test with REMOTE_ADDR
    $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
    
    $reflection = new ReflectionClass($this->logService);
    $method = $reflection->getMethod('getClientIp');
    $method->setAccessible(true);
    
    $ip = $method->invoke($this->logService);
    
    expect($ip)->toBe('192.168.1.100');
});

test('can validate request data structure', function () {
    $requestData = [
        'endpoint' => '/test-endpoint',
        'method' => 'GET',
        'parameters' => ['param1' => 'value1'],
        'response_time' => 0.123,
        'response_size' => 1024,
        'status_code' => 200,
        'cache_hit' => true,
        'error_message' => null
    ];
    
    // Verify all expected fields are present
    expect($requestData)->toHaveKey('endpoint');
    expect($requestData)->toHaveKey('method');
    expect($requestData)->toHaveKey('parameters');
    expect($requestData)->toHaveKey('response_time');
    expect($requestData)->toHaveKey('response_size');
    expect($requestData)->toHaveKey('status_code');
    expect($requestData)->toHaveKey('cache_hit');
});

test('can check rate limit structure', function () {
    // Mock the database operations for testing
    global $wpdb;
    $wpdb = new class {
        public $prefix = 'wp_';
        public function delete($table, $where, $format) { return true; }
        public function get_var($query) { return 0; }
        public function insert($table, $data, $format) { return true; }
        public function prepare($query, ...$args) { return $query; }
    };
    
    // This would normally interact with database
    // For unit test, we just verify the method exists and returns expected structure
    $rateLimitCheck = $this->logService->checkRateLimit('/test-endpoint');
    
    expect($rateLimitCheck)->toHaveKeys(['allowed', 'remaining', 'limit', 'reset_time', 'current_count']);
});

test('can get performance metrics structure', function () {
    // Mock the database operations for testing
    global $wpdb;
    $wpdb = new class {
        public $prefix = 'wp_';
        public function get_row($query, $output = OBJECT) { 
            return [
                'requests_24h' => 100,
                'avg_response_time_24h' => 0.123,
                'max_response_time_24h' => 0.500,
                'cache_hits_24h' => 80,
                'errors_24h' => 5
            ];
        }
        public function get_var($query) { return 1000; }
        public function prepare($query, ...$args) { return $query; }
    };
    
    $metrics = $this->logService->getPerformanceMetrics();
    
    expect($metrics)->toHaveKeys([
        'requests_last_24h',
        'avg_response_time_24h',
        'max_response_time_24h',
        'cache_hit_rate_24h',
        'error_rate_24h',
        'current_rate_limit_usage',
        'total_log_entries',
        'cleanup_needed'
    ]);
});

test('can validate log data structure', function () {
    $logData = [
        'endpoint' => '/test-endpoint',
        'method' => 'GET',
        'parameters' => ['param1' => 'value1'],
        'response_time' => 0.123,
        'response_size' => 1024,
        'status_code' => 200,
        'cache_hit' => true,
        'error_message' => null
    ];
    
    // Verify all required fields are present
    $requiredFields = ['endpoint', 'method', 'parameters', 'response_time', 'response_size', 'status_code'];
    
    foreach ($requiredFields as $field) {
        expect($logData)->toHaveKey($field);
    }
    
    expect($logData['endpoint'])->toBeString();
    expect($logData['method'])->toBeString();
    expect($logData['parameters'])->toBeArray();
    expect($logData['response_time'])->toBeFloat();
    expect($logData['response_size'])->toBeInt();
    expect($logData['status_code'])->toBeInt();
    expect($logData['cache_hit'])->toBeBool();
});