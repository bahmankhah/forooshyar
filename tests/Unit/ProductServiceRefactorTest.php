<?php

use Tests\TestCase;
use Forooshyar\Services\ProductService;
use Forooshyar\Services\ConfigService;
use Forooshyar\Services\TitleBuilder;

/**
 * Test the refactored ProductService with clean architecture
 */

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

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        // Mock error logging
        return true;
    }
}

describe('ProductService Refactor', function () {
    
    test('ProductService can be instantiated with dependencies', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        $productService = new ProductService($configService, $titleBuilder);
        
        expect($productService)->toBeInstanceOf(ProductService::class);
    });
    
    test('ProductService handles invalid parameters gracefully', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        $productService = new ProductService($configService, $titleBuilder);
        
        // Test with invalid limit
        $result = $productService->getProducts(['limit' => -1]);
        
        expect($result)->toHaveKey('error');
        expect($result['count'])->toBe(0);
        expect($result['products'])->toBeArray();
        expect($result['products'])->toBeEmpty();
    });
    
    test('ProductService handles invalid product IDs gracefully', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        $productService = new ProductService($configService, $titleBuilder);
        
        // Test with invalid product IDs
        $result = $productService->getProductsFromIds(['invalid', null, '']);
        
        expect($result)->toHaveKey('products');
        expect($result['products'])->toBeArray();
    });
    
    test('ProductService validates input parameters', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        $productService = new ProductService($configService, $titleBuilder);
        
        // Test with invalid page number
        $result = $productService->getProducts(['page' => 0]);
        
        expect($result)->toHaveKey('error');
        expect($result['count'])->toBe(0);
        
        // Test with excessive limit
        $result = $productService->getProducts(['limit' => 2000]);
        
        expect($result)->toHaveKey('error');
        expect($result['count'])->toBe(0);
    });
    
    test('ProductService uses dependency injection correctly', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        
        // Verify that ProductService requires both dependencies
        expect(function() {
            new ProductService();
        })->toThrow(ArgumentCountError::class);
        
        // Verify that it works with proper dependencies
        $productService = new ProductService($configService, $titleBuilder);
        expect($productService)->toBeInstanceOf(ProductService::class);
    });
    
});