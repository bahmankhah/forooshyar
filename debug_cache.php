<?php

require_once __DIR__ . '/vendor/autoload.php';

use Forooshyar\Services\CacheService;
use Forooshyar\Services\ConfigService;

// Mock WordPress functions
class MockWordPressDebug {
    private static $options = [];
    private static $transients = [];
    
    public static function getOption($option, $default = false) {
        return self::$options[$option] ?? $default;
    }
    
    public static function updateOption($option, $value) {
        self::$options[$option] = $value;
        return true;
    }
    
    public static function getTransient($transient) {
        echo "Getting transient: $transient\n";
        return self::$transients[$transient] ?? false;
    }
    
    public static function setTransient($transient, $value, $expiration = 0) {
        echo "Setting transient: $transient\n";
        self::$transients[$transient] = $value;
        return true;
    }
    
    public static function getTransients() {
        return self::$transients;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return MockWordPressDebug::getOption($option, $default);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return MockWordPressDebug::updateOption($option, $value);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return MockWordPressDebug::getTransient($transient);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return MockWordPressDebug::setTransient($transient, $value, $expiration);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/';
    }
}

// Test the cache
$configService = new ConfigService();
$configService->set('cache', ['enabled' => true, 'ttl' => 3600]);

$cacheService = new CacheService($configService);

echo "Cache enabled: " . ($configService->get('cache')['enabled'] ? 'true' : 'false') . "\n";

// Test setting and getting
$key = 'product_123';
$data = ['id' => 123, 'name' => 'Test Product'];

echo "Setting cache with key: $key\n";
$setResult = $cacheService->set($key, $data);
echo "Set result: " . ($setResult ? 'true' : 'false') . "\n";

echo "Getting cache with key: $key\n";
$getResult = $cacheService->get($key);
echo "Get result: " . json_encode($getResult) . "\n";

echo "All transients: " . json_encode(MockWordPressDebug::getTransients()) . "\n";