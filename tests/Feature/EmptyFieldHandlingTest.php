<?php

use Forooshyar\Resources\ProductResource;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 13: Empty field handling consistency
 * 
 * For any product response, registry and guarantee fields should be empty strings (not null) when no data is available
 * 
 * Validates: Requirements 14.8
 */

it('ensures empty fields are returned as empty strings not null', function () {
    // Test with completely empty product data
    $emptyProductData = [
        'title' => '',
        'subtitle' => null,
        'parent_id' => 0,
        'page_unique' => 123,
        'current_price' => null,
        'old_price' => '',
        'availability' => null,
        'category_name' => '',
        'image_links' => [],
        'image_link' => null,
        'page_url' => '',
        'short_desc' => null,
        'spec' => [],
        'date' => null,
        'registry' => null,
        'guarantee' => null
    ];
    
    $resource = new ProductResource($emptyProductData);
    $result = $resource->toArray();
    
    // All string fields should be empty strings, not null
    expect($result['title'])->toBe('');
    expect($result['subtitle'])->toBe('');
    expect($result['current_price'])->toBe('');
    expect($result['old_price'])->toBe('');
    expect($result['availability'])->toBe('');
    expect($result['category_name'])->toBe('');
    expect($result['image_link'])->toBe('');
    expect($result['page_url'])->toBe('');
    expect($result['short_desc'])->toBe('');
    expect($result['registry'])->toBe('');
    expect($result['guarantee'])->toBe('');
    
    // Arrays should be empty arrays, not null
    expect($result['image_links'])->toBe([]);
    expect($result['spec'])->toBe([]);
    expect($result['date'])->toBe([]);
    
    // Numeric fields should be proper integers
    expect($result['parent_id'])->toBe(0);
    expect($result['page_unique'])->toBe(123);
});

it('handles mixed null and empty values consistently', function () {
    $mixedData = [
        'title' => 'Test Product',
        'subtitle' => null,
        'parent_id' => 0,
        'page_unique' => 456,
        'current_price' => '',
        'old_price' => null,
        'availability' => 'instock',
        'category_name' => null,
        'image_links' => null,
        'image_link' => '',
        'page_url' => 'https://example.com/product',
        'short_desc' => null,
        'spec' => null,
        'date' => null,
        'registry' => '',
        'guarantee' => null
    ];
    
    $resource = new ProductResource($mixedData);
    $result = $resource->toArray();
    
    // Null values should become empty strings for string fields
    expect($result['subtitle'])->toBe('');
    expect($result['old_price'])->toBe('');
    expect($result['category_name'])->toBe('');
    expect($result['short_desc'])->toBe('');
    expect($result['guarantee'])->toBe('');
    
    // Empty strings should remain empty strings
    expect($result['current_price'])->toBe('');
    expect($result['image_link'])->toBe('');
    expect($result['registry'])->toBe('');
    
    // Null arrays should become empty arrays
    expect($result['image_links'])->toBe([]);
    expect($result['spec'])->toBe([]);
    expect($result['date'])->toBe([]);
    
    // Valid values should be preserved
    expect($result['title'])->toBe('Test Product');
    expect($result['availability'])->toBe('instock');
    expect($result['page_url'])->toBe('https://example.com/product');
});

it('preserves non-empty values while converting empty ones correctly', function () {
    $productData = [
        'title' => 'Valid Product',
        'subtitle' => 'Valid Subtitle',
        'parent_id' => 100,
        'page_unique' => 789,
        'current_price' => '99.99',
        'old_price' => '149.99',
        'availability' => 'instock',
        'category_name' => 'Electronics',
        'image_links' => ['https://example.com/image1.jpg', 'https://example.com/image2.jpg'],
        'image_link' => 'https://example.com/main.jpg',
        'page_url' => 'https://example.com/valid-product',
        'short_desc' => 'A valid product description',
        'spec' => [['color' => 'red', 'size' => 'large']],
        'date' => ['date' => '2023-01-01 12:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
        'registry' => null, // This should become empty string
        'guarantee' => ''   // This should remain empty string
    ];
    
    $resource = new ProductResource($productData);
    $result = $resource->toArray();
    
    // Valid values should be preserved exactly
    expect($result['title'])->toBe('Valid Product');
    expect($result['subtitle'])->toBe('Valid Subtitle');
    expect($result['parent_id'])->toBe(100);
    expect($result['page_unique'])->toBe(789);
    expect($result['current_price'])->toBe('99.99');
    expect($result['old_price'])->toBe('149.99');
    expect($result['availability'])->toBe('instock');
    expect($result['category_name'])->toBe('Electronics');
    expect($result['image_links'])->toBe(['https://example.com/image1.jpg', 'https://example.com/image2.jpg']);
    expect($result['image_link'])->toBe('https://example.com/main.jpg');
    expect($result['page_url'])->toBe('https://example.com/valid-product');
    expect($result['short_desc'])->toBe('A valid product description');
    expect($result['spec'])->toBe([['color' => 'red', 'size' => 'large']]);
    expect($result['date'])->toBe(['date' => '2023-01-01 12:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC']);
    
    // Empty/null fields should be empty strings
    expect($result['registry'])->toBe('');
    expect($result['guarantee'])->toBe('');
});

it('handles missing fields by providing default empty values', function () {
    // Product data with some fields missing entirely
    $incompleteData = [
        'title' => 'Incomplete Product',
        'page_unique' => 999,
        'current_price' => '50.00'
        // Many fields are missing
    ];
    
    $resource = new ProductResource($incompleteData);
    $result = $resource->toArray();
    
    // Present fields should be preserved
    expect($result['title'])->toBe('Incomplete Product');
    expect($result['page_unique'])->toBe(999);
    expect($result['current_price'])->toBe('50.00');
    
    // Missing fields should get appropriate default values
    expect($result['subtitle'])->toBe('');
    expect($result['parent_id'])->toBe(0);
    expect($result['old_price'])->toBe('');
    expect($result['availability'])->toBe('');
    expect($result['category_name'])->toBe('');
    expect($result['image_links'])->toBe([]);
    expect($result['image_link'])->toBe('');
    expect($result['page_url'])->toBe('');
    expect($result['short_desc'])->toBe('');
    expect($result['spec'])->toBe([]);
    expect($result['date'])->toBe([]);
    expect($result['registry'])->toBe('');
    expect($result['guarantee'])->toBe('');
});

describe('Empty Field Handling - Property-Based Tests', function () {
    
    /**
     * Feature: woocommerce-product-refactor, Property 13: Empty field handling consistency
     * 
     * For any product response, registry and guarantee fields should be empty strings (not null) when no data is available
     * 
     * **Validates: Requirements 14.8**
     */
    test('property-based test: empty fields should consistently be empty strings not null', function () {
        $faker = Faker::create();
        
        // Generate multiple test iterations to increase confidence
        for ($i = 0; $i < 100; $i++) {
            // Generate product data with random mix of empty, null, and valid values
            $productData = [
                'title' => $faker->optional(0.7)->words(3, true) ?: '',
                'subtitle' => $faker->optional(0.6)->words(2, true),
                'parent_id' => $faker->numberBetween(0, 1000),
                'page_unique' => $faker->numberBetween(1, 10000),
                'current_price' => $faker->optional(0.8)->randomFloat(2, 10, 1000) ? (string) $faker->randomFloat(2, 10, 1000) : null,
                'old_price' => $faker->optional(0.5)->randomFloat(2, 15, 1200) ? (string) $faker->randomFloat(2, 15, 1200) : '',
                'availability' => $faker->optional(0.9)->randomElement(['instock', 'outofstock', 'onbackorder']),
                'category_name' => $faker->optional(0.7)->words(2, true),
                'image_links' => $faker->optional(0.8)->randomElements([
                    'https://example.com/image1.jpg',
                    'https://example.com/image2.jpg',
                    'https://example.com/image3.jpg'
                ], $faker->numberBetween(0, 3)),
                'image_link' => $faker->optional(0.7)->imageUrl(800, 600, 'products', true),
                'page_url' => $faker->optional(0.8)->url(),
                'short_desc' => $faker->optional(0.6)->sentence(),
                'spec' => $faker->optional(0.7)->randomElements([
                    ['color' => $faker->colorName()],
                    ['size' => $faker->randomElement(['S', 'M', 'L', 'XL'])],
                    ['material' => $faker->word()]
                ], $faker->numberBetween(0, 3)),
                'date' => $faker->optional(0.8)->dateTime(),
                'registry' => $faker->optional(0.3)->word(), // Low probability to test empty handling
                'guarantee' => $faker->optional(0.3)->sentence() // Low probability to test empty handling
            ];
            
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Core property: All string fields should be strings, never null
            expect($result['title'])->toBeString();
            expect($result['subtitle'])->toBeString();
            expect($result['current_price'])->toBeString();
            expect($result['old_price'])->toBeString();
            expect($result['availability'])->toBeString();
            expect($result['category_name'])->toBeString();
            expect($result['image_link'])->toBeString();
            expect($result['page_url'])->toBeString();
            expect($result['short_desc'])->toBeString();
            expect($result['registry'])->toBeString();
            expect($result['guarantee'])->toBeString();
            
            // Specifically test registry and guarantee (the focus of Requirement 14.8)
            // These should NEVER be null, always empty strings when no data
            expect($result['registry'])->not->toBeNull();
            expect($result['guarantee'])->not->toBeNull();
            
            // Array fields should be arrays, never null
            expect($result['image_links'])->toBeArray();
            expect($result['spec'])->toBeArray();
            expect($result['date'])->toBeArray();
            
            // Numeric fields should be integers
            expect($result['parent_id'])->toBeInt();
            expect($result['page_unique'])->toBeInt();
        }
    })->repeat(5); // Run this property test multiple times to increase confidence
    
    /**
     * Feature: woocommerce-product-refactor, Property 13: Empty field handling consistency
     * 
     * For any product with null or missing registry/guarantee data, these fields should be empty strings
     * 
     * **Validates: Requirements 14.8**
     */
    test('property-based test: registry and guarantee fields specifically handle null as empty strings', function () {
        $faker = Faker::create();
        
        // Generate multiple test cases focusing on registry and guarantee fields
        for ($i = 0; $i < 50; $i++) {
            // Create test cases with various null/empty combinations for registry and guarantee
            $registryValue = $faker->randomElement([null, '', $faker->word(), $faker->optional(0.2)->word()]);
            $guaranteeValue = $faker->randomElement([null, '', $faker->sentence(), $faker->optional(0.2)->sentence()]);
            
            $productData = [
                'title' => $faker->words(3, true),
                'subtitle' => $faker->words(2, true),
                'parent_id' => $faker->numberBetween(0, 100),
                'page_unique' => $faker->numberBetween(1, 1000),
                'current_price' => (string) $faker->randomFloat(2, 10, 100),
                'old_price' => (string) $faker->randomFloat(2, 15, 120),
                'availability' => 'instock',
                'category_name' => $faker->word(),
                'image_links' => ['https://example.com/image.jpg'],
                'image_link' => 'https://example.com/main.jpg',
                'page_url' => $faker->url(),
                'short_desc' => $faker->sentence(),
                'spec' => [['color' => 'red']],
                'date' => new DateTime(),
                'registry' => $registryValue,
                'guarantee' => $guaranteeValue
            ];
            
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // The core property: registry and guarantee should ALWAYS be strings
            expect($result['registry'])->toBeString();
            expect($result['guarantee'])->toBeString();
            
            // When input is null, output should be empty string (not null)
            if ($registryValue === null) {
                expect($result['registry'])->toBe('');
            }
            if ($guaranteeValue === null) {
                expect($result['guarantee'])->toBe('');
            }
            
            // When input is empty string, output should remain empty string
            if ($registryValue === '') {
                expect($result['registry'])->toBe('');
            }
            if ($guaranteeValue === '') {
                expect($result['guarantee'])->toBe('');
            }
            
            // When input has a value, it should be preserved as string
            if ($registryValue !== null && $registryValue !== '') {
                expect($result['registry'])->toBe((string) $registryValue);
            }
            if ($guaranteeValue !== null && $guaranteeValue !== '') {
                expect($result['guarantee'])->toBe((string) $guaranteeValue);
            }
        }
    });
});