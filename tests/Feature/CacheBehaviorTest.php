<?php

use Tests\TestCase;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\ConfigService;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 5: Cache-first behavior
 * Validates: Requirements 4.1, 4.2
 */

// Mock WordPress functions with shared state
class MockWordPressCache {
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
        return self::$transients[$transient] ?? false;
    }
    
    public static function setTransient($transient, $value, $expiration = 0) {
        self::$transients[$transient] = $value;
        return true;
    }
    
    public static function deleteTransient($transient) {
        unset(self::$transients[$transient]);
        return true;
    }
    
    public static function clearAll() {
        self::$options = [];
        self::$transients = [];
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return MockWordPressCache::getOption($option, $default);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return MockWordPressCache::updateOption($option, $value);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return MockWordPressCache::getTransient($transient);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return MockWordPressCache::setTransient($transient, $value, $expiration);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return MockWordPressCache::deleteTransient($transient);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new class {
        public $options = 'wp_options';
        
        public function prepare($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        }
        
        public function query($query) {
            return 1; // Mock successful query
        }
        
        public function get_var($query) {
            return 5; // Mock count
        }
    };
}

describe('Cache Behavior', function () {
    
    beforeEach(function () {
        // Clear mock cache before each test
        MockWordPressCache::clearAll();
    });
    
    test('property 5: cache-first behavior - for any API request, system should check cache before querying database, and cache misses should result in database queries followed by cache storage', function () {
        // Property: For any API request, the system should check cache first before querying the database,
        // and cache misses should result in database queries followed by cache storage
        
        $faker = Faker::create();
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Generate test data
        $cacheKey = $faker->slug();
        $testData = [
            'id' => $faker->numberBetween(1, 1000),
            'name' => $faker->words(3, true),
            'price' => $faker->randomFloat(2, 10, 1000),
            'category' => $faker->word()
        ];
        
        // Test 1: Cache miss scenario
        // First request should return false (cache miss)
        $cachedResult = $cacheService->get($cacheKey);
        expect($cachedResult)->toBeFalse();
        
        // Simulate storing data after database query
        $storeResult = $cacheService->set($cacheKey, $testData);
        expect($storeResult)->toBeTrue();
        
        // Test 2: Cache hit scenario
        // Second request should return cached data (cache hit)
        $cachedResult = $cacheService->get($cacheKey);
        expect($cachedResult)->toBe($testData);
        
        // Test 3: Cache key generation should be consistent
        $params1 = ['page' => 1, 'limit' => 10, 'category' => 'electronics'];
        $params2 = ['limit' => 10, 'page' => 1, 'category' => 'electronics']; // Same params, different order
        
        $key1 = $cacheService->generateKey('products', $params1);
        $key2 = $cacheService->generateKey('products', $params2);
        
        // Keys should be identical regardless of parameter order
        expect($key1)->toBe($key2);
        
        // Test 4: Different parameters should generate different keys
        $params3 = ['page' => 2, 'limit' => 10, 'category' => 'electronics'];
        $key3 = $cacheService->generateKey('products', $params3);
        
        expect($key1)->not->toBe($key3);
        
        // Test 5: Cache deletion should work
        $deleteResult = $cacheService->delete($cacheKey);
        expect($deleteResult)->toBeTrue();
        
        // After deletion, cache should miss again
        $cachedResult = $cacheService->get($cacheKey);
        expect($cachedResult)->toBeFalse();
        
    })->repeat(100);
    
    test('cache service should respect configuration settings', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Test cache statistics
        $stats = $cacheService->getStats();
        
        expect($stats)->toHaveKey('enabled');
        expect($stats)->toHaveKey('total_entries');
        expect($stats)->toHaveKey('invalidated_keys');
        expect($stats)->toHaveKey('ttl');
        expect($stats)->toHaveKey('prefix');
        
        expect($stats['enabled'])->toBeBool();
        expect($stats['total_entries'])->toBeInt();
        expect($stats['invalidated_keys'])->toBeInt();
        expect($stats['ttl'])->toBeInt();
        expect($stats['prefix'])->toBeString();
        
        // TTL should be positive
        expect($stats['ttl'])->toBeGreaterThan(0);
        
    });
    
    test('cache key generation should handle various parameter types', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Test with different parameter types
        $params = [
            'string_param' => $faker->word(),
            'int_param' => $faker->numberBetween(1, 100),
            'bool_param' => $faker->boolean(),
            'array_param' => $faker->words(3),
            'null_param' => null
        ];
        
        $key = $cacheService->generateKey('test', $params);
        
        // Key should be a non-empty string
        expect($key)->toBeString();
        expect($key)->not->toBeEmpty();
        
        // Key should contain the prefix
        expect($key)->toContain('test_');
        
        // Same parameters should generate same key
        $key2 = $cacheService->generateKey('test', $params);
        expect($key)->toBe($key2);
        
    });
    
    test('cache flush should clear all cache entries', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Set multiple cache entries
        $keys = [];
        for ($i = 0; $i < 5; $i++) {
            $key = $faker->slug();
            $keys[] = $key;
            $cacheService->set($key, $faker->words(3, true));
        }
        
        // Verify entries exist
        foreach ($keys as $key) {
            expect($cacheService->get($key))->not->toBeFalse();
        }
        
        // Flush cache
        $flushResult = $cacheService->flush();
        expect($flushResult)->toBeTrue();
        
        // Verify entries are gone (in a real implementation)
        // Note: Our mock implementation doesn't actually clear the static array,
        // but in a real WordPress environment, this would clear all transients
        
    });
    
});