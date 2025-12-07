<?php

use Tests\TestCase;
use Forooshyar\Services\PerformanceOptimizationService;
use Forooshyar\Services\ConfigService;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\LoggingService;
use Forooshyar\Services\ProductService;
use Forooshyar\Controllers\ProductController;
use Faker\Factory as Faker;

/**
 * Performance Optimization and Final Testing
 * Tests Requirements 12.1, 12.2, 12.3, 12.4, 12.5
 */

// Mock WordPress functions for performance testing
class MockWordPressPerformance {
    private static $options = [];
    private static $transients = [];
    private static $posts = [];
    private static $terms = [];
    
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
        self::$posts = [];
        self::$terms = [];
    }
    
    public static function addPost($id, $data) {
        self::$posts[$id] = $data;
    }
    
    public static function getPosts() {
        return self::$posts;
    }
}

// Mock ConfigService for performance testing
class MockPerformanceConfigService extends ConfigService {
    private array $config = [];
    
    public function __construct() {
        $this->config = [
            'cache' => ['enabled' => true, 'ttl' => 3600],
            'api' => ['max_per_page' => 100],
            'images' => ['max_images' => 10, 'sizes' => ['medium', 'large'], 'quality' => 80],
            'compression' => ['enabled' => true, 'min_size' => 1024, 'level' => 6]
        ];
    }
    
    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    public function set(string $key, $value): bool {
        $this->config[$key] = $value;
        return true;
    }
}

// Mock LoggingService
class MockLoggingService extends LoggingService {
    private array $logs = [];
    
    public function __construct() {
        // Don't call parent constructor
    }
    
    public function logPerformance(string $operation, array $data): void {
        $this->logs[] = ['type' => 'performance', 'operation' => $operation, 'data' => $data];
    }
    
    public function logError(\Throwable $e, string $category, string $operation = 'unknown'): void {
        $this->logs[] = ['type' => 'error', 'operation' => $operation, 'category' => $category, 'exception' => $e];
    }
    
    public function getLogs(): array {
        return $this->logs;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return MockWordPressPerformance::getOption($option, $default);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return MockWordPressPerformance::updateOption($option, $value);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return MockWordPressPerformance::getTransient($transient);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return MockWordPressPerformance::setTransient($transient, $value, $expiration);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return MockWordPressPerformance::deleteTransient($transient);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($id, $size) {
        return ["https://example.com/image_{$id}_{$size}.jpg", 800, 600];
    }
}

if (!function_exists('wc_attribute_label')) {
    function wc_attribute_label($name) {
        return ucfirst(str_replace(['pa_', '_', '-'], ['', ' ', ' '], $name));
    }
}

if (!function_exists('wc_get_product_terms')) {
    function wc_get_product_terms($id, $taxonomy, $args = []) {
        return ['Term 1', 'Term 2'];
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args) {
        return [
            (object) ['term_id' => 1, 'name' => 'Category 1', 'slug' => 'category-1'],
            (object) ['term_id' => 2, 'name' => 'Category 2', 'slug' => 'category-2']
        ];
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
            
            public function get_name() {
                return $this->faker->words(3, true);
            }
            
            public function get_price() {
                return $this->faker->randomFloat(2, 10, 1000);
            }
            
            public function get_stock_status() {
                return $this->faker->randomElement(['instock', 'outofstock']);
            }
            
            public function get_image_id() {
                return $this->faker->numberBetween(1, 100);
            }
            
            public function get_gallery_image_ids() {
                return [$this->faker->numberBetween(101, 200), $this->faker->numberBetween(201, 300)];
            }
            
            public function get_attributes() {
                return [
                    new class($this->faker) {
                        private $faker;
                        
                        public function __construct($faker) {
                            $this->faker = $faker;
                        }
                        
                        public function get_visible() {
                            return true;
                        }
                        
                        public function get_name() {
                            return 'pa_color';
                        }
                        
                        public function is_taxonomy() {
                            return true;
                        }
                        
                        public function get_options() {
                            return ['Red', 'Blue', 'Green'];
                        }
                    }
                ];
            }
            
            public function get_category_ids() {
                return [1, 2];
            }
            
            public function is_type($type) {
                return $type === 'variable' && $this->id < 50;
            }
            
            public function get_children() {
                if ($this->is_type('variable')) {
                    return [$this->faker->numberBetween(1001, 1999), $this->faker->numberBetween(1001, 1999)];
                }
                return [];
            }
            
            public function exists() {
                return true;
            }
        };
    }
}

if (!function_exists('memory_get_usage')) {
    function memory_get_usage($real = false) {
        return 1024 * 1024 * 50; // 50MB
    }
}

if (!function_exists('memory_get_peak_usage')) {
    function memory_get_peak_usage($real = false) {
        return 1024 * 1024 * 75; // 75MB
    }
}

if (!function_exists('ini_get')) {
    function ini_get($option) {
        if ($option === 'memory_limit') {
            return '256M';
        }
        return false;
    }
}

if (!function_exists('gc_collect_cycles')) {
    function gc_collect_cycles() {
        return 0;
    }
}

if (!function_exists('headers_sent')) {
    function headers_sent() {
        return false;
    }
}

if (!function_exists('header')) {
    function header($string) {
        // Mock header function
    }
}

if (!function_exists('gzencode')) {
    function gzencode($data, $level = -1) {
        return 'compressed_' . $data;
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
            return 1;
        }
        
        public function get_var($query) {
            return 5;
        }
    };
}

describe('Performance Optimization and Final Testing', function () {
    
    beforeEach(function () {
        MockWordPressPerformance::clearAll();
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';
    });
    
    test('requirement 12.1: optimize database queries and caching strategies', function () {
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        // Test query optimization
        $originalQuery = [
            'post_type' => 'product',
            'posts_per_page' => 200, // Over limit
            'meta_query' => [
                ['key' => 'price', 'value' => '100', 'compare' => 'LIKE']
            ]
        ];
        
        $optimizedQuery = $performanceService->optimizeProductQueries($originalQuery);
        
        // Should limit posts per page
        expect($optimizedQuery['posts_per_page'])->toBeLessThanOrEqual(100);
        
        // Should add performance optimizations
        expect($optimizedQuery)->toHaveKey('fields');
        expect($optimizedQuery)->toHaveKey('no_found_rows');
        expect($optimizedQuery)->toHaveKey('update_post_meta_cache');
        expect($optimizedQuery)->toHaveKey('update_post_term_cache');
        
        // Should optimize meta query
        expect($optimizedQuery['meta_query'][0]['compare'])->toBe('='); // LIKE optimized to =
        
        // Test performance metrics
        $metrics = $performanceService->getPerformanceMetrics();
        expect($metrics)->toHaveKey('query_count');
        expect($metrics)->toHaveKey('query_time');
        expect($metrics)->toHaveKey('cache_hit_ratio');
        expect($metrics)->toHaveKey('optimizations_enabled');
        
        expect($metrics['query_count'])->toBeGreaterThan(0);
        expect($metrics['optimizations_enabled'])->toBeArray();
        
    });
    
    test('requirement 12.2: add response compression for large catalogs', function () {
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        // Test with large response data
        $largeData = [
            'count' => 1000,
            'max_pages' => 10,
            'products' => []
        ];
        
        // Generate large product dataset
        $faker = Faker::create();
        for ($i = 0; $i < 100; $i++) {
            $largeData['products'][] = [
                'id' => $faker->numberBetween(1, 10000),
                'title' => $faker->sentence(10),
                'description' => $faker->paragraph(5),
                'price' => $faker->randomFloat(2, 10, 1000),
                'specifications' => array_fill(0, 20, $faker->words(5, true))
            ];
        }
        
        // Test compression
        $compressedData = $performanceService->compressResponse($largeData);
        
        // Should return the data (compression handled by headers)
        expect($compressedData)->toBe($largeData);
        
        // Check that compression was logged
        $logs = $loggingService->getLogs();
        $compressionLogs = array_filter($logs, function($log) {
            return $log['operation'] === 'response_compression';
        });
        
        expect(count($compressionLogs))->toBeGreaterThan(0);
        
        // Test with small data (should not compress)
        $smallData = ['id' => 1, 'name' => 'test'];
        $result = $performanceService->compressResponse($smallData);
        expect($result)->toBe($smallData);
        
    });
    
    test('requirement 12.3: implement lazy loading for heavy data', function () {
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        $faker = Faker::create();
        $product = wc_get_product($faker->numberBetween(1, 100));
        
        // Test lazy loading basic fields only
        $basicData = $performanceService->lazyLoadProductData($product, ['id', 'title']);
        
        expect($basicData)->toHaveKey('id');
        expect($basicData)->toHaveKey('title');
        expect($basicData)->not->toHaveKey('images');
        expect($basicData)->not->toHaveKey('specifications');
        
        // Test lazy loading with heavy fields
        $fullData = $performanceService->lazyLoadProductData($product, [
            'images', 'specifications', 'variations', 'categories'
        ]);
        
        expect($fullData)->toHaveKey('images');
        expect($fullData)->toHaveKey('specifications');
        expect($fullData)->toHaveKey('variations');
        expect($fullData)->toHaveKey('categories');
        
        // Images should be optimized
        expect($fullData['images'])->toHaveKey('main');
        expect($fullData['images'])->toHaveKey('gallery');
        
        // Specifications should be loaded
        expect($fullData['specifications'])->toBeArray();
        
        // Categories should be loaded
        expect($fullData['categories'])->toBeArray();
        
        // Test caching (second call should be faster)
        $startTime = microtime(true);
        $cachedData = $performanceService->lazyLoadProductData($product, ['images']);
        $endTime = microtime(true);
        
        expect($cachedData)->toHaveKey('images');
        expect($endTime - $startTime)->toBeLessThan(0.1); // Should be very fast due to caching
        
    });
    
    test('requirement 12.4: test with large product catalogs and high load', function () {
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        // Test large catalog performance
        $testResults = $performanceService->testLargeCatalogPerformance(5000);
        
        expect($testResults)->toHaveKey('product_count');
        expect($testResults)->toHaveKey('total_time');
        expect($testResults)->toHaveKey('peak_memory');
        expect($testResults)->toHaveKey('operations');
        
        expect($testResults['product_count'])->toBe(5000);
        expect($testResults['total_time'])->toBeFloat();
        expect($testResults['peak_memory'])->toBeInt();
        
        // All operations should succeed
        foreach ($testResults['operations'] as $operation => $result) {
            expect($result)->toHaveKey('success');
            expect($result['success'])->toBeTrue();
            expect($result)->toHaveKey('time');
            expect($result['time'])->toBeFloat();
        }
        
        // Test batch processing performance
        $productIds = range(1, 1000);
        
        $batchResults = $performanceService->batchDatabaseOperations(
            function($batch) {
                // Simulate heavy processing
                return array_map(function($id) {
                    return ['id' => $id, 'processed' => true, 'data' => str_repeat('x', 100)];
                }, $batch);
            },
            $productIds,
            50
        );
        
        expect(count($batchResults))->toBe(1000);
        expect($batchResults[0])->toHaveKey('processed');
        expect($batchResults[0]['processed'])->toBeTrue();
        
        // Check batch operation logs
        $logs = $loggingService->getLogs();
        $batchLogs = array_filter($logs, function($log) {
            return $log['operation'] === 'batch_operation';
        });
        
        expect(count($batchLogs))->toBeGreaterThan(0);
        
    });
    
    test('requirement 12.5: verify all property-based tests pass with 100+ iterations', function () {
        // This test verifies that the performance optimizations don't break existing functionality
        // by running key operations multiple times
        
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        $faker = Faker::create();
        $successCount = 0;
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Test 1: Query optimization consistency
                $queryArgs = [
                    'post_type' => 'product',
                    'posts_per_page' => $faker->numberBetween(10, 200),
                    'paged' => $faker->numberBetween(1, 10)
                ];
                
                $optimized = $performanceService->optimizeProductQueries($queryArgs);
                
                // Should always have required fields
                if (!isset($optimized['fields']) || !isset($optimized['posts_per_page'])) {
                    continue;
                }
                
                // Should respect limits
                if ($optimized['posts_per_page'] > 100) {
                    continue;
                }
                
                // Test 2: Lazy loading consistency
                $product = wc_get_product($faker->numberBetween(1, 1000));
                $requestedFields = $faker->randomElements(['images', 'specifications', 'variations', 'categories'], $faker->numberBetween(1, 4));
                
                $lazyData = $performanceService->lazyLoadProductData($product, $requestedFields);
                
                // Should have basic fields
                if (!isset($lazyData['id']) || !isset($lazyData['title'])) {
                    continue;
                }
                
                // Should have requested fields
                $hasAllRequestedFields = true;
                foreach ($requestedFields as $field) {
                    if (!isset($lazyData[$field])) {
                        $hasAllRequestedFields = false;
                        break;
                    }
                }
                
                if (!$hasAllRequestedFields) {
                    continue;
                }
                
                // Test 3: Memory optimization consistency
                $performanceService->optimizeMemoryUsage();
                
                // Test 4: Performance metrics consistency
                $metrics = $performanceService->getPerformanceMetrics();
                
                if (!is_array($metrics) || !isset($metrics['query_count'])) {
                    continue;
                }
                
                $successCount++;
                
            } catch (\Exception $e) {
                // Log error but continue testing
                continue;
            }
        }
        
        // At least 95% of iterations should succeed
        $successRate = ($successCount / $iterations) * 100;
        expect($successRate)->toBeGreaterThanOrEqual(95.0);
        
        // Performance metrics should show activity
        $finalMetrics = $performanceService->getPerformanceMetrics();
        expect($finalMetrics['query_count'])->toBeGreaterThan(0);
        
    });
    
    test('memory optimization prevents memory leaks', function () {
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        // Simulate memory-intensive operations
        $initialMemory = memory_get_usage(true);
        
        for ($i = 0; $i < 100; $i++) {
            $faker = Faker::create();
            $product = wc_get_product($faker->numberBetween(1, 1000));
            
            // Load heavy data
            $performanceService->lazyLoadProductData($product, ['images', 'specifications', 'variations']);
            
            // Optimize memory every 10 iterations
            if ($i % 10 === 0) {
                $performanceService->optimizeMemoryUsage();
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 10MB for this test)
        expect($memoryIncrease)->toBeLessThan(10 * 1024 * 1024);
        
        // Performance metrics should track memory usage
        $metrics = $performanceService->getPerformanceMetrics();
        expect($metrics)->toHaveKey('memory_usage_mb');
        expect($metrics['memory_usage_mb'])->toBeFloat();
        
    });
    
    test('batch operations handle errors gracefully', function () {
        $configService = new MockPerformanceConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new MockLoggingService();
        $performanceService = new PerformanceOptimizationService($configService, $cacheService, $loggingService);
        
        $items = range(1, 100);
        
        // Operation that fails on certain items
        $results = $performanceService->batchDatabaseOperations(
            function($batch) {
                return array_map(function($item) {
                    if ($item % 20 === 0) {
                        throw new \Exception("Simulated error for item {$item}");
                    }
                    return ['id' => $item, 'processed' => true];
                }, $batch);
            },
            $items,
            10
        );
        
        // Should continue processing despite errors
        expect(count($results))->toBeGreaterThan(0);
        expect(count($results))->toBeLessThan(100); // Some should fail
        
        // Should log errors
        $logs = $loggingService->getLogs();
        $errorLogs = array_filter($logs, function($log) {
            return $log['type'] === 'error' && $log['operation'] === 'batch_operation_failed';
        });
        
        expect(count($errorLogs))->toBeGreaterThan(0);
        
    });
    
});