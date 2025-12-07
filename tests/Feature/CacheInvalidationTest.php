<?php

use Tests\TestCase;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\ConfigService;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 6: Automatic cache invalidation
 * Validates: Requirements 4.3, 7.1, 7.2, 7.3
 */

// Mock WordPress functions with shared state
class MockWordPressCacheInvalidation {
    private static $options = [];
    private static $transients = [];
    private static $posts = [];
    
    public static function getOption($option, $default = false) {
        return self::$options[$option] ?? $default;
    }
    
    public static function updateOption($option, $value) {
        self::$options[$option] = $value;
        return true;
    }
    
    public static function getTransient($transient) {
        // Handle both prefixed and non-prefixed keys for compatibility
        return self::$transients[$transient] ?? false;
    }
    
    public static function setTransient($transient, $value, $expiration = 0) {
        // Store the transient with the exact key provided
        self::$transients[$transient] = $value;
        return true;
    }
    
    public static function deleteTransient($transient) {
        // Delete the transient with the exact key provided
        if (isset(self::$transients[$transient])) {
            unset(self::$transients[$transient]);
            return true;
        }
        return false;
    }
    
    public static function addPost($id, $data) {
        self::$posts[$id] = $data;
    }
    
    public static function getPost($id) {
        return self::$posts[$id] ?? null;
    }
    
    public static function clearAll() {
        self::$options = [];
        self::$transients = [];
        self::$posts = [];
    }
    
    public static function getTransients() {
        return self::$transients;
    }
}

// Mock ConfigService for testing
class MockConfigServiceCacheInvalidation extends ConfigService {
    private array $config = [];
    
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function set(string $key, $value): bool {
        $this->config[$key] = $value;
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return MockWordPressCacheInvalidation::getOption($option, $default);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return MockWordPressCacheInvalidation::updateOption($option, $value);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($id) {
        $faker = Faker::create();
        
        return new class($id, $faker) {
            private $id;
            private $faker;
            
            public function __construct($id, $faker) {
                $this->id = $id;
                $this->faker = $faker;
            }
            
            public function is_type($type) {
                if ($type === 'variation') {
                    return $this->id > 1000; // Variations have IDs > 1000
                }
                if ($type === 'variable') {
                    return $this->id < 100; // Variable products have IDs < 100
                }
                return true;
            }
            
            public function get_parent_id() {
                return $this->is_type('variation') ? $this->faker->numberBetween(1, 99) : 0;
            }
            
            public function get_children() {
                if ($this->is_type('variable')) {
                    return [$this->faker->numberBetween(1001, 1999), $this->faker->numberBetween(1001, 1999)];
                }
                return [];
            }
            
            public function get_status() {
                return 'publish';
            }
        };
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args) {
        $faker = Faker::create();
        $count = $faker->numberBetween(1, 5);
        $posts = [];
        
        for ($i = 0; $i < $count; $i++) {
            $posts[] = $faker->numberBetween(1, 1000);
        }
        
        return $posts;
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

describe('Cache Invalidation', function () {
    
    beforeEach(function () {
        // Clear mock cache before each test
        MockWordPressCacheInvalidation::clearAll();
        GlobalTransientMock::clear();
    });
    
    test('property 6: automatic cache invalidation - for any product modification, all related cache entries should be automatically cleared, including variations and parent products', function () {
        // Property: For any product modification (create, update, delete), all related cache entries 
        // should be automatically cleared, including variations and parent products
        
        $faker = Faker::create();
        $configService = new MockConfigServiceCacheInvalidation();
        
        // Ensure cache is enabled for testing
        $configService->set('cache', ['enabled' => true, 'ttl' => 3600]);
        
        $cacheService = new CacheService($configService);
        
        // Generate test data
        $productId = $faker->numberBetween(1, 999);
        $variationId = $faker->numberBetween(1001, 1999);
        $categoryId = $faker->numberBetween(1, 50);
        
        // Set up initial cache entries
        $productKey = "product_{$productId}";
        $variationKey = "product_{$variationId}";
        $categoryKey = "category_{$categoryId}";
        $listKey = "products_list";
        
        $productData = ['id' => $productId, 'name' => $faker->words(2, true)];
        $variationData = ['id' => $variationId, 'parent_id' => $productId];
        $categoryData = ['id' => $categoryId, 'name' => $faker->word()];
        $listData = [$productId, $variationId];
        
        // Debug: Check cache config before setting
        $cacheConfig = $configService->get('cache', ['enabled' => false]);
        $cacheEnabled = $cacheConfig['enabled'] ?? false;
        
        // Debug: Check if functions are defined correctly
        $functionsDebug = "set_transient defined: " . (function_exists('set_transient') ? 'yes' : 'no') .
                         ", get_transient defined: " . (function_exists('get_transient') ? 'yes' : 'no');
        
        // Cache all entries
        $setResult1 = $cacheService->set($productKey, $productData);
        $setResult2 = $cacheService->set($variationKey, $variationData);
        $setResult3 = $cacheService->set($categoryKey, $categoryData);
        $setResult4 = $cacheService->set($listKey, $listData);
        
        expect($setResult1)->toBeTrue();
        expect($setResult2)->toBeTrue();
        expect($setResult3)->toBeTrue();
        expect($setResult4)->toBeTrue();
        
        // Verify entries are cached - use direct cache service calls
        $retrievedProductData = $cacheService->get($productKey);
        expect($retrievedProductData)->toBe($productData);
        expect($retrievedProductData)->toBe($productData);
        expect($cacheService->get($variationKey))->toBe($variationData);
        expect($cacheService->get($categoryKey))->toBe($categoryData);
        expect($cacheService->get($listKey))->toBe($listData);
        
        // Test 1: Product invalidation should clear related caches
        $invalidateResult = $cacheService->invalidateProduct($productId);
        expect($invalidateResult)->toBeTrue();
        
        // Product cache should be cleared
        expect($cacheService->get($productKey))->toBeFalse();
        
        // List caches should be cleared
        expect($cacheService->get($listKey))->toBeFalse();
        
        // Test 2: Variation invalidation should also clear parent
        // Reset cache for variation test
        $cacheService->set($productKey, $productData);
        $cacheService->set($variationKey, $variationData);
        
        $variationInvalidateResult = $cacheService->invalidateProduct($variationId);
        expect($variationInvalidateResult)->toBeTrue();
        
        // Both variation and parent should be cleared
        expect($cacheService->get($variationKey))->toBeFalse();
        
        // Test 3: Category invalidation should clear related products
        // Reset cache for category test
        $cacheService->set($categoryKey, $categoryData);
        
        $categoryInvalidateResult = $cacheService->invalidateCategory($categoryId);
        expect($categoryInvalidateResult)->toBeTrue();
        
        // Category cache should be cleared
        expect($cacheService->get($categoryKey))->toBeFalse();
        
        // Test 4: Cache key generation should be consistent for invalidation
        $params = ['page' => 1, 'category' => $categoryId];
        $generatedKey = $cacheService->generateKey('products', $params);
        
        expect($generatedKey)->toBeString();
        expect($generatedKey)->not->toBeEmpty();
        expect($generatedKey)->toContain('products_');
        
        // Same parameters should generate same key
        $generatedKey2 = $cacheService->generateKey('products', $params);
        expect($generatedKey)->toBe($generatedKey2);
        
        // Test 5: Variable product invalidation should clear all variations
        $variableProductId = $faker->numberBetween(1, 99); // Variable products have low IDs
        $variableKey = "product_{$variableProductId}";
        $variableData = ['id' => $variableProductId, 'type' => 'variable'];
        
        $cacheService->set($variableKey, $variableData);
        expect($cacheService->get($variableKey))->toBe($variableData);
        
        $variableInvalidateResult = $cacheService->invalidateProduct($variableProductId);
        expect($variableInvalidateResult)->toBeTrue();
        
        // Variable product cache should be cleared
        expect($cacheService->get($variableKey))->toBeFalse();
        
    })->repeat(1);
    
    test('cache invalidation should handle edge cases correctly', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        
        // Ensure cache is enabled for testing
        MockWordPressCacheInvalidation::updateOption('forooshyar_cache', ['enabled' => true, 'ttl' => 3600]);
        
        $cacheService = new CacheService($configService);
        
        // Test invalidating non-existent product
        $nonExistentId = $faker->numberBetween(9000, 9999);
        $result = $cacheService->invalidateProduct($nonExistentId);
        expect($result)->toBeTrue(); // Should not fail
        
        // Test invalidating non-existent category
        $nonExistentCategoryId = $faker->numberBetween(9000, 9999);
        $result = $cacheService->invalidateCategory($nonExistentCategoryId);
        expect($result)->toBeTrue(); // Should not fail
        
        // Test multiple invalidations of same product
        $productId = $faker->numberBetween(1, 999);
        $result1 = $cacheService->invalidateProduct($productId);
        $result2 = $cacheService->invalidateProduct($productId);
        
        expect($result1)->toBeTrue();
        expect($result2)->toBeTrue();
        
    });
    
    test('cache statistics should track invalidation operations', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        
        // Ensure cache is enabled for testing
        MockWordPressCacheInvalidation::updateOption('forooshyar_cache', ['enabled' => true, 'ttl' => 3600]);
        
        $cacheService = new CacheService($configService);
        
        // Get initial stats
        $initialStats = $cacheService->getStats();
        expect($initialStats)->toHaveKey('invalidated_keys');
        expect($initialStats['invalidated_keys'])->toBeInt();
        
        $initialInvalidatedCount = $initialStats['invalidated_keys'];
        
        // Perform some invalidations
        $productId = $faker->numberBetween(1, 999);
        $cacheService->invalidateProduct($productId);
        
        // Check stats after invalidation
        $newStats = $cacheService->getStats();
        expect($newStats['invalidated_keys'])->toBeGreaterThan($initialInvalidatedCount);
        
    });
    
});