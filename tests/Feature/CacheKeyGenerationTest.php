<?php

use Forooshyar\Services\CacheService;
use Forooshyar\Services\ConfigService;
use Faker\Factory as Faker;

describe('Cache Key Generation - Property-Based Tests', function () {
    
    beforeEach(function () {
        // Mock WordPress functions
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
    });

    /**
     * **Feature: woocommerce-product-refactor, Property 7: Cache key uniqueness**
     * **Validates: Requirements 4.4**
     */
    test('property-based test: cache key uniqueness for different parameter sets', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        $generatedKeys = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random parameters that would be used in API requests
            $params1 = [
                'page' => $faker->numberBetween(1, 50),
                'limit' => $faker->numberBetween(10, 100),
                'show_variations' => $faker->boolean(),
                'category' => $faker->optional()->numberBetween(1, 20),
                'search' => $faker->optional()->word(),
                'orderby' => $faker->randomElement(['date', 'title', 'price']),
                'order' => $faker->randomElement(['asc', 'desc'])
            ];
            
            $params2 = [
                'page' => $faker->numberBetween(1, 50),
                'limit' => $faker->numberBetween(10, 100),
                'show_variations' => $faker->boolean(),
                'category' => $faker->optional()->numberBetween(1, 20),
                'search' => $faker->optional()->word(),
                'orderby' => $faker->randomElement(['date', 'title', 'price']),
                'order' => $faker->randomElement(['asc', 'desc'])
            ];
            
            $prefix = 'products';
            $key1 = $cacheService->generateKey($prefix, $params1);
            $key2 = $cacheService->generateKey($prefix, $params2);
            
            // Property: Different parameter sets should generate different cache keys
            if ($params1 !== $params2) {
                expect($key1)->not->toBe($key2, 
                    "Different parameters should generate different cache keys. Params1: " . json_encode($params1) . 
                    ", Params2: " . json_encode($params2)
                );
            }
            
            // Property: Same parameters should generate same cache key
            $key1_duplicate = $cacheService->generateKey($prefix, $params1);
            expect($key1)->toBe($key1_duplicate, 
                "Same parameters should generate identical cache keys"
            );
            
            // Property: Cache key should include all relevant parameters
            expect($key1)->toStartWith($prefix, "Cache key should start with the prefix");
            expect($key1)->toMatch('/^' . preg_quote($prefix, '/') . '_[a-f0-9]{32}$/', 
                "Cache key should follow the pattern: prefix_hash"
            );
            
            // Track generated keys to ensure uniqueness across iterations
            if (isset($generatedKeys[$key1])) {
                // Only fail if the parameters are actually different
                if ($generatedKeys[$key1] !== $params1) {
                    expect(false)->toBeTrue(
                        "Cache key collision detected for different parameters. " .
                        "Key: {$key1}, Original params: " . json_encode($generatedKeys[$key1]) . 
                        ", New params: " . json_encode($params1)
                    );
                }
            } else {
                $generatedKeys[$key1] = $params1;
            }
        }
        
        // Property: Parameter order should not affect cache key generation
        $baseParams = [
            'page' => 1,
            'limit' => 20,
            'show_variations' => true,
            'category' => 5
        ];
        
        $reorderedParams = [
            'category' => 5,
            'show_variations' => true,
            'limit' => 20,
            'page' => 1
        ];
        
        $key1 = $cacheService->generateKey('test', $baseParams);
        $key2 = $cacheService->generateKey('test', $reorderedParams);
        
        expect($key1)->toBe($key2, 
            "Parameter order should not affect cache key generation"
        );
        
    })->repeat(5); // Run this property test multiple times to increase confidence
    
    /**
     * **Feature: woocommerce-product-refactor, Property 7: Cache key uniqueness**
     * **Validates: Requirements 4.4**
     */
    test('property-based test: cache key includes pagination and filter parameters', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        for ($i = 0; $i < 50; $i++) {
            $page = $faker->numberBetween(1, 100);
            $limit = $faker->numberBetween(1, 200);
            $showVariations = $faker->boolean();
            $categoryId = $faker->optional()->numberBetween(1, 50);
            
            $params = array_filter([
                'page' => $page,
                'limit' => $limit,
                'show_variations' => $showVariations,
                'category_id' => $categoryId
            ], function($value) {
                return $value !== null;
            });
            
            $cacheKey = $cacheService->generateKey('products', $params);
            
            // Property: Cache key should be deterministic for same parameters
            $duplicateKey = $cacheService->generateKey('products', $params);
            expect($cacheKey)->toBe($duplicateKey, 
                "Cache key should be deterministic for identical parameters"
            );
            
            // Property: Changing any parameter should change the cache key
            $modifiedParams = $params;
            $modifiedParams['page'] = $page + 1;
            $modifiedKey = $cacheService->generateKey('products', $modifiedParams);
            
            expect($cacheKey)->not->toBe($modifiedKey, 
                "Changing pagination should result in different cache key"
            );
            
            // Property: Cache key should be a valid string with expected format
            expect($cacheKey)->toBeString();
            expect(strlen($cacheKey))->toBeGreaterThan(10, 
                "Cache key should be sufficiently long to avoid collisions"
            );
        }
    });
});