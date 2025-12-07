<?php

use Forooshyar\Middleware\ApiLoggingMiddleware;
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
    
    // Mock global $wpdb
    global $wpdb;
    $wpdb = new class {
        public $prefix = 'wp_';
        public function delete($table, $where, $format) { return true; }
        public function get_var($query) { return 0; }
        public function insert($table, $data, $format) { return true; }
        public function prepare($query, ...$args) { return $query; }
        public function get_row($query, $output = OBJECT) { 
            return [
                'requests_24h' => 100,
                'avg_response_time_24h' => 0.123,
                'max_response_time_24h' => 0.500,
                'cache_hits_24h' => 80,
                'errors_24h' => 5
            ];
        }
        public function get_results($query, $output = OBJECT) { return []; }
        public function query($query) { return true; }
    };
    
    // Mock WP_REST_Request and WP_REST_Response
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request {
            private $params = [];
            private $route = '/test';
            private $method = 'GET';
            
            public function get_params() { return $this->params; }
            public function get_route() { return $this->route; }
            public function get_method() { return $this->method; }
            public function set_param($key, $value) { $this->params[$key] = $value; }
        }
    }
    
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            private $data = [];
            private $status = 200;
            private $headers = [];
            
            public function __construct($data = null, $status = 200) {
                $this->data = $data;
                $this->status = $status;
            }
            
            public function get_data() { return $this->data; }
            public function get_status() { return $this->status; }
            public function get_headers() { return $this->headers; }
            public function header($key, $value) { $this->headers[$key] = $value; }
        }
    }
    
    // Create test instances
    $this->configService = new ConfigService();
    $this->logService = new ApiLogService($this->configService, false); // Don't create tables in tests
    $this->middleware = new ApiLoggingMiddleware($this->logService);
});

test('can create middleware instance', function () {
    expect($this->middleware)->toBeInstanceOf(ApiLoggingMiddleware::class);
});

test('can handle successful request', function () {
    $request = new WP_REST_Request();
    $request->set_param('test_param', 'test_value');
    
    $response = $this->middleware->handle($request, function($req) {
        return new WP_REST_Response(['success' => true], 200);
    });
    
    expect($response)->toBeInstanceOf(WP_REST_Response::class);
    expect($response->get_status())->toBe(200);
    expect($response->get_data())->toHaveKey('success');
});

test('adds rate limit headers to response', function () {
    $request = new WP_REST_Request();
    
    $response = $this->middleware->handle($request, function($req) {
        return new WP_REST_Response(['success' => true], 200);
    });
    
    $headers = $response->get_headers();
    
    expect($headers)->toHaveKey('X-RateLimit-Limit');
    expect($headers)->toHaveKey('X-RateLimit-Remaining');
    expect($headers)->toHaveKey('X-RateLimit-Reset');
});

test('can detect cache hit from global variable', function () {
    // Set global cache hit indicator
    $GLOBALS['forooshyar_cache_hit'] = true;
    
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('detectCacheHit');
    $method->setAccessible(true);
    
    $response = new WP_REST_Response(['test' => 'data']);
    $cacheHit = $method->invoke($this->middleware, $response);
    
    expect($cacheHit)->toBeTrue();
    
    // Clean up
    unset($GLOBALS['forooshyar_cache_hit']);
});

test('can sanitize sensitive parameters', function () {
    $reflection = new ReflectionClass($this->middleware);
    $method = $reflection->getMethod('sanitizeParameters');
    $method->setAccessible(true);
    
    $parameters = [
        'username' => 'testuser',
        'password' => 'secret123',
        'api_key' => 'abc123def456',
        'normal_data' => 'public_info'
    ];
    
    $sanitized = $method->invoke($this->middleware, $parameters);
    
    expect($sanitized['username'])->toBe('testuser');
    expect($sanitized['password'])->toBe('[REDACTED]');
    expect($sanitized['api_key'])->toBe('[REDACTED]');
    expect($sanitized['normal_data'])->toBe('public_info');
});

test('can create middleware using static factory', function () {
    $middleware = ApiLoggingMiddleware::create();
    
    expect($middleware)->toBeInstanceOf(ApiLoggingMiddleware::class);
});

test('handles error responses correctly', function () {
    $request = new WP_REST_Request();
    
    $response = $this->middleware->handle($request, function($req) {
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => 'TEST_ERROR',
                'message' => 'Test error message'
            ]
        ], 400);
    });
    
    expect($response->get_status())->toBe(400);
    expect($response->get_data()['success'])->toBeFalse();
    expect($response->get_data()['error']['code'])->toBe('TEST_ERROR');
});