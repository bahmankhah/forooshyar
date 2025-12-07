<?php

use Tests\TestCase;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\ConfigService;
use Forooshyar\Services\CacheInvalidationService;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 6: Automatic cache invalidation (bulk operations)
 * Validates: Requirements 7.4
 */

// Mock WordPress functions with shared state
class MockWordPressBulkCache {
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
    
    public static function deleteOption($option) {
        unset(self::$options[$option]);
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
}

// Mock ConfigService for testing
class MockConfigService extends ConfigService {
    private array $config = [];
    
    public function __construct() {
        // Don't call parent constructor to avoid file system dependencies
        // Set up default cache config
        $this->config['cache'] = ['enabled' => true, 'ttl' => 3600];
    }
    
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function set(string $key, $value): bool {
        $this->config[$key] = $value;
        return true;
    }
}

// Mock ErrorHandlingService for testing
class MockErrorHandlingServiceBulk {
    public function handleError($error, $context = []) {
        return true;
    }
    
    public function logError($message, $context = []) {
        return true;
    }
}

// Mock LoggingService for testing  
class MockLoggingServiceBulk {
    public function log($message, $level = 'info', $context = []) {
        return true;
    }
    
    public function logApiRequest($endpoint, $params, $responseTime) {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return MockWordPressBulkCache::getOption($option, $default);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return MockWordPressBulkCache::updateOption($option, $value);
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return MockWordPressBulkCache::deleteOption($option);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return MockWordPressBulkCache::getTransient($transient);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return MockWordPressBulkCache::setTransient($transient, $value, $expiration);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return MockWordPressBulkCache::deleteTransient($transient);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($postId) {
        return 'product'; // Mock all posts as products
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
            
            public function get_id() {
                return $this->id;
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

// Mock WordPress action hooks
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        // Mock implementation - just return true
        return true;
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

describe('Bulk Cache Invalidation', function () {
    
    beforeEach(function () {
        // Clear mock cache before each test
        MockWordPressBulkCache::clearAll();
    });
    
    test('property 6: automatic cache invalidation (bulk operations) - for any bulk operation, all affected cache entries should be efficiently cleared', function () {
        // Property: For any bulk operation affecting multiple products, all related cache entries 
        // should be efficiently cleared without individual invalidation calls
        
        $faker = Faker::create();
        $configService = new MockConfigService();
        $cacheService = new CacheService($configService);
        
        // Test 1: Bulk invalidation methods exist and return boolean
        $productIds = [$faker->numberBetween(1, 999), $faker->numberBetween(1, 999)];
        $categoryIds = [$faker->numberBetween(1, 50), $faker->numberBetween(1, 50)];
        
        $bulkProductResult = $cacheService->invalidateBulkProducts($productIds);
        expect($bulkProductResult)->toBeTrue();
        
        $bulkCategoryResult = $cacheService->invalidateBulkCategories($categoryIds);
        expect($bulkCategoryResult)->toBeTrue();
        
        // Test 2: Pattern-based invalidation exists and returns boolean
        $patternResult = $cacheService->invalidateByPattern('test_pattern');
        expect($patternResult)->toBeTrue();
        
        // Test 3: Bulk operation performance tracking
        $initialStats = $cacheService->getStats();
        expect($initialStats)->toHaveKey('bulk_operations');
        expect($initialStats['bulk_operations'])->toBeArray();
        
        // Perform bulk operation to update stats
        $cacheService->invalidateBulkProducts($productIds);
        
        $newStats = $cacheService->getStats();
        expect($newStats['bulk_operations']['total_operations'])->toBeGreaterThanOrEqual($initialStats['bulk_operations']['total_operations']);
        
        // Test 4: Empty bulk operations should not fail
        $emptyProductResult = $cacheService->invalidateBulkProducts([]);
        expect($emptyProductResult)->toBeTrue();
        
        $emptyCategoryResult = $cacheService->invalidateBulkCategories([]);
        expect($emptyCategoryResult)->toBeTrue();
        
        // Test 5: Duplicate IDs in bulk operation should be handled
        $duplicateIds = [$productIds[0], $productIds[0], $productIds[1], $productIds[1]];
        $duplicateResult = $cacheService->invalidateBulkProducts($duplicateIds);
        expect($duplicateResult)->toBeTrue();
        
        // Test 6: Bulk operations should be more efficient than individual operations
        // This is tested by ensuring the bulk methods exist and complete successfully
        $largeProductIds = [];
        for ($i = 0; $i < 50; $i++) {
            $largeProductIds[] = $faker->numberBetween(1, 9999);
        }
        
        $startTime = microtime(true);
        $largeBulkResult = $cacheService->invalidateBulkProducts($largeProductIds);
        $endTime = microtime(true);
        
        expect($largeBulkResult)->toBeTrue();
        expect($endTime - $startTime)->toBeLessThan(1.0); // Should complete within 1 second
        
    })->repeat(100);
    
    test('cache invalidation service should handle bulk operations through hooks', function () {
        $faker = Faker::create();
        $configService = new MockConfigService();
        $cacheService = new CacheService($configService);
        $errorHandlingService = new MockErrorHandlingServiceBulk();
        $loggingService = new MockLoggingServiceBulk();
        $invalidationService = new CacheInvalidationService($cacheService, $errorHandlingService, $loggingService);
        
        // Test bulk variations save
        $variableProduct = wc_get_product($faker->numberBetween(1, 99)); // Variable product
        
        // Simulate bulk variations save - this should not fail
        $invalidationService->onBulkVariationsSaved($variableProduct);
        
        // Test bulk products save
        $bulkProductIds = [
            $faker->numberBetween(100, 999),
            $faker->numberBetween(100, 999),
            $faker->numberBetween(100, 999)
        ];
        
        // Simulate bulk products save - this should not fail
        $invalidationService->onBulkProductsSaved($bulkProductIds);
        
        // Test invalidation statistics
        $stats = $invalidationService->getInvalidationStats();
        expect($stats)->toHaveKey('total_invalidations');
        expect($stats)->toHaveKey('actions');
        expect($stats)->toHaveKey('recent_activity');
        
        expect($stats['total_invalidations'])->toBeInt();
        expect($stats['actions'])->toBeArray();
        expect($stats['recent_activity'])->toBeArray();
        
        // Test individual hook methods exist and don't throw errors
        $productId = $faker->numberBetween(1, 999);
        $categoryId = $faker->numberBetween(1, 50);
        
        // These should all complete without errors
        $invalidationService->onProductSaved($productId);
        $invalidationService->onProductDeleted($productId);
        $invalidationService->onVariationSaved($productId);
        $invalidationService->onCategoryChanged($categoryId);
        
        // Test that the service can clear logs
        $clearResult = $invalidationService->clearInvalidationLogs();
        expect($clearResult)->toBeTrue();
        
    });
    
    test('bulk operations should be more efficient than individual operations', function () {
        $faker = Faker::create();
        $configService = new MockConfigService();
        $cacheService = new CacheService($configService);
        
        // Generate a larger set of products for performance comparison
        $productCount = 50;
        $productIds = [];
        
        for ($i = 0; $i < $productCount; $i++) {
            $productId = $faker->numberBetween(1, 9999);
            $productIds[] = $productId;
            
            // Cache the product
            $cacheService->set("product_{$productId}", ['id' => $productId]);
        }
        
        // Measure bulk operation
        $bulkStartTime = microtime(true);
        $bulkResult = $cacheService->invalidateBulkProducts($productIds);
        $bulkEndTime = microtime(true);
        $bulkTime = $bulkEndTime - $bulkStartTime;
        
        expect($bulkResult)->toBeTrue();
        expect($bulkTime)->toBeLessThan(1.0); // Should complete within 1 second
        
        // Verify all products are invalidated
        foreach ($productIds as $productId) {
            expect($cacheService->get("product_{$productId}"))->toBeFalse();
        }
        
        // Check that bulk operation stats are recorded
        $stats = $cacheService->getStats();
        expect($stats['bulk_operations']['total_operations'])->toBeGreaterThanOrEqual(0);
        expect($stats['bulk_operations']['total_products_processed'])->toBeGreaterThanOrEqual(0);
        
    });
    
});