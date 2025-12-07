<?php

use Forooshyar\Resources\ProductResource;

/**
 * Feature: woocommerce-product-refactor, Property 14: URL encoding consistency
 * 
 * For any product URL generation, variation parameters should be properly URL-encoded in the query string
 * 
 * Validates: Requirements 6.7, 14.9
 */

it('ensures variation parameters are properly URL encoded in page_url', function () {
    // Test with special characters that need URL encoding
    $productDataWithSpecialChars = [
        'title' => 'Test Product',
        'subtitle' => '',
        'parent_id' => 100,
        'page_unique' => 123,
        'current_price' => '99.99',
        'old_price' => '149.99',
        'availability' => 'instock',
        'category_name' => 'Electronics',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product/test-product/?attribute_color=red%20blue&attribute_size=large%2Fmedium&attribute_material=cotton%26polyester',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataWithSpecialChars);
    $result = $resource->toArray();
    
    // URL should be preserved as-is (already encoded)
    expect($result['page_url'])->toBe('https://example.com/product/test-product/?attribute_color=red%20blue&attribute_size=large%2Fmedium&attribute_material=cotton%26polyester');
    
    // Verify that the URL contains properly encoded special characters
    expect($result['page_url'])->toContain('%20'); // space encoded
    expect($result['page_url'])->toContain('%2F'); // forward slash encoded
    expect($result['page_url'])->toContain('%26'); // ampersand encoded
});

it('handles URLs with Persian characters in variation parameters', function () {
    $productDataWithPersian = [
        'title' => 'محصول تست',
        'subtitle' => '',
        'parent_id' => 200,
        'page_unique' => 456,
        'current_price' => '50.00',
        'old_price' => '75.00',
        'availability' => 'instock',
        'category_name' => 'لوازم خانگی',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product/test/?attribute_رنگ=%D8%A2%D8%A8%DB%8C&attribute_سایز=%D8%A8%D8%B2%D8%B1%DA%AF',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataWithPersian);
    $result = $resource->toArray();
    
    // Persian characters should be properly URL encoded
    expect($result['page_url'])->toBe('https://example.com/product/test/?attribute_رنگ=%D8%A2%D8%A8%DB%8C&attribute_سایز=%D8%A8%D8%B2%D8%B1%DA%AF');
    
    // Verify URL contains encoded Persian characters
    expect($result['page_url'])->toContain('%D8%'); // Persian character encoding pattern
});

it('preserves URL encoding for complex variation parameter combinations', function () {
    $productDataComplex = [
        'title' => 'Complex Product',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 789,
        'current_price' => '199.99',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Complex Category',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/shop/product-name/?attribute_pa_color=red%2Bblue&attribute_pa_size=x-large&attribute_custom-field=value%20with%20spaces&utm_source=test',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataComplex);
    $result = $resource->toArray();
    
    // Complex URL with multiple encoded parameters should be preserved
    expect($result['page_url'])->toBe('https://example.com/shop/product-name/?attribute_pa_color=red%2Bblue&attribute_pa_size=x-large&attribute_custom-field=value%20with%20spaces&utm_source=test');
    
    // Verify specific encoding patterns
    expect($result['page_url'])->toContain('red%2Bblue'); // plus sign encoded
    expect($result['page_url'])->toContain('value%20with%20spaces'); // spaces encoded
    expect($result['page_url'])->toContain('attribute_pa_'); // pa_ prefix preserved
});

it('handles empty or malformed URLs gracefully', function () {
    $productDataEmptyUrl = [
        'title' => 'Product with Empty URL',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 999,
        'current_price' => '25.00',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => '',
        'image_links' => [],
        'image_link' => '',
        'page_url' => '', // Empty URL
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataEmptyUrl);
    $result = $resource->toArray();
    
    // Empty URL should remain empty string
    expect($result['page_url'])->toBe('');
});

it('preserves URL structure for simple products without variations', function () {
    $simpleProductData = [
        'title' => 'Simple Product',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 111,
        'current_price' => '15.00',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Simple',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product/simple-product/',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($simpleProductData);
    $result = $resource->toArray();
    
    // Simple product URL without parameters should be preserved as-is
    expect($result['page_url'])->toBe('https://example.com/product/simple-product/');
    
    // Should not contain any query parameters
    expect($result['page_url'])->not->toContain('?');
    expect($result['page_url'])->not->toContain('attribute_');
});

it('handles URLs with both variation and tracking parameters', function () {
    $productDataMixed = [
        'title' => 'Tracked Product',
        'subtitle' => '',
        'parent_id' => 300,
        'page_unique' => 555,
        'current_price' => '89.99',
        'old_price' => '99.99',
        'availability' => 'instock',
        'category_name' => 'Tracked',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product/tracked/?attribute_color=blue%20green&utm_source=google&utm_medium=cpc&attribute_size=medium',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataMixed);
    $result = $resource->toArray();
    
    // URL with both variation and tracking parameters should be preserved
    expect($result['page_url'])->toBe('https://example.com/product/tracked/?attribute_color=blue%20green&utm_source=google&utm_medium=cpc&attribute_size=medium');
    
    // Should contain both types of parameters
    expect($result['page_url'])->toContain('attribute_color=blue%20green');
    expect($result['page_url'])->toContain('utm_source=google');
    expect($result['page_url'])->toContain('attribute_size=medium');
});

describe('URL Encoding - Property-Based Tests', function () {
    
    /**
     * Feature: woocommerce-product-refactor, Property 14: URL encoding consistency
     * 
     * For any product URL generation, variation parameters should be properly URL-encoded in the query string
     * 
     * **Validates: Requirements 6.7, 14.9**
     */
    test('property-based test: URL encoding should be preserved for all variation parameter combinations', function () {
        $faker = \Faker\Factory::create();
        
        // Generate random product data with various URL encoding scenarios
        for ($i = 0; $i < 20; $i++) {
            // Create base product data
            $productData = [
                'title' => $faker->words(3, true),
                'subtitle' => $faker->optional()->sentence(),
                'parent_id' => $faker->numberBetween(0, 1000),
                'page_unique' => $faker->numberBetween(1, 9999),
                'current_price' => $faker->randomFloat(2, 1, 1000),
                'old_price' => $faker->optional()->randomFloat(2, 1, 1000),
                'availability' => $faker->randomElement(['instock', 'outofstock', 'onbackorder']),
                'category_name' => $faker->words(2, true),
                'image_links' => [],
                'image_link' => '',
                'short_desc' => $faker->optional()->sentence(),
                'spec' => [],
                'date' => [],
                'registry' => '',
                'guarantee' => ''
            ];
            
            // Generate URLs with various encoding scenarios
            $baseUrl = $faker->url() . '/product/' . $faker->slug();
            
            // Test different URL scenarios
            $urlScenarios = [
                // Simple URL without parameters
                $baseUrl . '/',
                
                // URL with spaces (should be %20)
                $baseUrl . '/?attribute_color=' . urlencode('red blue'),
                
                // URL with special characters
                $baseUrl . '/?attribute_size=' . urlencode('large/medium') . '&attribute_material=' . urlencode('cotton&polyester'),
                
                // URL with Persian characters
                $baseUrl . '/?attribute_رنگ=' . urlencode('آبی') . '&attribute_سایز=' . urlencode('بزرگ'),
                
                // URL with plus signs and other special chars
                $baseUrl . '/?attribute_pa_color=' . urlencode('red+blue') . '&attribute_custom=' . urlencode('value with spaces'),
                
                // Complex URL with multiple parameters
                $baseUrl . '/?attribute_color=' . urlencode($faker->colorName()) . '&attribute_size=' . urlencode($faker->randomElement(['small', 'medium', 'large', 'x-large'])) . '&utm_source=test'
            ];
            
            $productData['page_url'] = $faker->randomElement($urlScenarios);
            
            // Create resource and test
            $resource = new \Forooshyar\Resources\ProductResource($productData);
            $result = $resource->toArray();
            
            // Property: URL should be preserved exactly as provided (already encoded)
            expect($result['page_url'])->toBe($productData['page_url']);
            
            // Property: URL should be a string
            expect($result['page_url'])->toBeString();
            
            // Property: If URL contains query parameters, they should be properly formatted
            if (strpos($result['page_url'], '?') !== false) {
                $urlParts = parse_url($result['page_url']);
                
                // Should have valid URL structure
                expect($urlParts)->toHaveKey('scheme');
                expect($urlParts)->toHaveKey('host');
                
                // Query string should be properly formatted if present
                if (isset($urlParts['query'])) {
                    // Should not contain unencoded spaces (should be %20)
                    expect($urlParts['query'])->not->toContain(' ');
                    
                    // Should contain proper parameter separators
                    if (strpos($urlParts['query'], '&') !== false) {
                        $params = explode('&', $urlParts['query']);
                        foreach ($params as $param) {
                            expect($param)->toContain('=');
                        }
                    }
                }
            }
            
            // Property: Empty URLs should remain empty
            if (empty($productData['page_url'])) {
                expect($result['page_url'])->toBe('');
            }
        }
    })->repeat(5); // Run this property test multiple times to increase confidence
    
    /**
     * Feature: woocommerce-product-refactor, Property 14: URL encoding consistency
     * 
     * For any product URL with variation attributes, the encoding should be preserved consistently
     * 
     * **Validates: Requirements 6.7, 14.9**
     */
    test('property-based test: URL encoding should be preserved exactly as provided', function () {
        $faker = \Faker\Factory::create();
        
        // Test with pre-encoded URLs (as they would come from WooCommerce)
        $testUrls = [
            // URLs with %20 encoding for spaces
            'https://example.com/product/test/?attribute_color=red%20blue',
            'https://example.com/product/test/?attribute_size=large%20medium',
            
            // URLs with %2F encoding for slashes
            'https://example.com/product/test/?attribute_size=large%2Fmedium',
            'https://example.com/product/test/?attribute_type=size%2Fextra',
            
            // URLs with %26 encoding for ampersands
            'https://example.com/product/test/?attribute_material=cotton%26polyester',
            'https://example.com/product/test/?attribute_brand=brand%26model',
            
            // URLs with %2B encoding for plus signs
            'https://example.com/product/test/?attribute_color=red%2Bblue',
            'https://example.com/product/test/?attribute_size=size%2Bextra',
            
            // URLs with Persian character encoding
            'https://example.com/product/test/?attribute_رنگ=%D8%A2%D8%A8%DB%8C',
            'https://example.com/product/test/?attribute_سایز=%D8%A8%D8%B2%D8%B1%DA%AF',
            
            // URLs with special character encoding
            'https://example.com/product/test/?attribute_email=value%40domain.com',
            'https://example.com/product/test/?attribute_price=price%24100',
            'https://example.com/product/test/?attribute_id=size%231',
            
            // Complex URLs with multiple parameters
            'https://example.com/product/test/?attribute_color=red%20blue&attribute_size=large%2Fmedium&utm_source=test',
            'https://example.com/product/test/?attribute_pa_color=red%2Bblue&attribute_material=cotton%26polyester',
        ];
        
        // Test each URL type multiple times with random product data
        for ($i = 0; $i < 20; $i++) {
            $testUrl = $faker->randomElement($testUrls);
            
            // Randomize the base URL and product slug
            $baseUrl = $faker->url();
            $productSlug = $faker->slug();
            $testUrl = str_replace('https://example.com/product/test', $baseUrl . '/product/' . $productSlug, $testUrl);
            
            $productData = [
                'title' => $faker->words(3, true),
                'subtitle' => $faker->optional()->sentence(),
                'parent_id' => $faker->numberBetween(0, 1000),
                'page_unique' => $faker->numberBetween(1, 9999),
                'current_price' => $faker->randomFloat(2, 1, 1000),
                'old_price' => $faker->optional()->randomFloat(2, 1, 1000),
                'availability' => $faker->randomElement(['instock', 'outofstock', 'onbackorder']),
                'category_name' => $faker->words(2, true),
                'image_links' => [],
                'image_link' => '',
                'page_url' => $testUrl,
                'short_desc' => $faker->optional()->sentence(),
                'spec' => [],
                'date' => [],
                'registry' => '',
                'guarantee' => ''
            ];
            
            $resource = new \Forooshyar\Resources\ProductResource($productData);
            $result = $resource->toArray();
            
            // Property: URL encoding should be preserved exactly as provided
            expect($result['page_url'])->toBe($testUrl);
            
            // Property: URL should be a valid string
            expect($result['page_url'])->toBeString();
            
            // Property: URL structure should be maintained
            $originalParts = parse_url($testUrl);
            $resultParts = parse_url($result['page_url']);
            
            expect($resultParts['scheme'])->toBe($originalParts['scheme']);
            expect($resultParts['host'])->toBe($originalParts['host']);
            expect($resultParts['path'])->toBe($originalParts['path']);
            
            if (isset($originalParts['query'])) {
                expect($resultParts['query'])->toBe($originalParts['query']);
                
                // Property: Query parameters should not contain raw spaces
                expect($resultParts['query'])->not->toContain(' ');
            }
        }
    })->repeat(3); // Run this property test multiple times
});