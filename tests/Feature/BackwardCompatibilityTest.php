<?php

use Forooshyar\Resources\ProductResource;
use Forooshyar\Resources\ProductCollectionResource;

/**
 * Comprehensive backward compatibility test for task 15
 * 
 * Verifies all existing JSON field names and types are maintained
 * Tests proper empty field handling (empty strings vs null)
 * Validates URL encoding for variation parameters
 * Confirms registry and guarantee field extraction from specifications
 * 
 * Requirements: 14.6, 14.7, 14.8, 14.9, 6.9
 */

it('maintains exact JSON field names and structure for backward compatibility', function () {
    $productData = [
        'title' => 'Test Product',
        'subtitle' => 'Test Subtitle',
        'parent_id' => 100,
        'page_unique' => 123,
        'current_price' => '99.99',
        'old_price' => '149.99',
        'availability' => 'instock',
        'category_name' => 'Electronics',
        'image_links' => ['https://example.com/image1.jpg', 'https://example.com/image2.jpg'],
        'image_link' => 'https://example.com/main.jpg',
        'page_url' => 'https://example.com/product/test?attribute_color=red%20blue',
        'short_desc' => 'A test product description',
        'spec' => [['color' => 'red', 'size' => 'large', 'رجیستری' => 'دارد', 'گارانتی' => '12 ماه']],
        'date' => ['date' => '2023-01-01 12:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
        'registry' => 'دارد',
        'guarantee' => '12 ماه'
    ];
    
    $resource = new ProductResource($productData);
    $result = $resource->toArray();
    
    // Verify all required fields exist with exact names
    $requiredFields = [
        'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
        'old_price', 'availability', 'category_name', 'image_links',
        'image_link', 'page_url', 'short_desc', 'spec', 'date',
        'registry', 'guarantee'
    ];
    
    foreach ($requiredFields as $field) {
        expect($result)->toHaveKey($field);
    }
    
    // Verify field types for backward compatibility
    expect($result['title'])->toBeString();
    expect($result['subtitle'])->toBeString();
    expect($result['parent_id'])->toBeInt();
    expect($result['page_unique'])->toBeInt();
    expect($result['current_price'])->toBeString(); // Must be string, not number
    expect($result['old_price'])->toBeString(); // Must be string, not number
    expect($result['availability'])->toBeString();
    expect($result['category_name'])->toBeString();
    expect($result['image_links'])->toBeArray();
    expect($result['image_link'])->toBeString();
    expect($result['page_url'])->toBeString();
    expect($result['short_desc'])->toBeString();
    expect($result['spec'])->toBeArray();
    expect($result['date'])->toBeArray();
    expect($result['registry'])->toBeString();
    expect($result['guarantee'])->toBeString();
});

it('handles collection responses with exact structure', function () {
    $products = [
        [
            'title' => 'Product 1',
            'subtitle' => '',
            'parent_id' => 0,
            'page_unique' => 1,
            'current_price' => '50.00',
            'old_price' => '',
            'availability' => 'instock',
            'category_name' => 'Category 1',
            'image_links' => [],
            'image_link' => '',
            'page_url' => 'https://example.com/product1',
            'short_desc' => '',
            'spec' => [],
            'date' => [],
            'registry' => '',
            'guarantee' => ''
        ],
        [
            'title' => 'Product 2',
            'subtitle' => '',
            'parent_id' => 0,
            'page_unique' => 2,
            'current_price' => '75.00',
            'old_price' => '100.00',
            'availability' => 'instock',
            'category_name' => 'Category 2',
            'image_links' => ['https://example.com/img.jpg'],
            'image_link' => 'https://example.com/img.jpg',
            'page_url' => 'https://example.com/product2',
            'short_desc' => 'Description',
            'spec' => [['color' => 'blue']],
            'date' => ['date' => '2023-01-01 12:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
            'registry' => '',
            'guarantee' => ''
        ]
    ];
    
    $collection = ProductCollectionResource::make($products, 2, 1);
    $result = $collection->toArray();
    
    // Verify collection structure
    expect($result)->toHaveKey('count');
    expect($result)->toHaveKey('max_pages');
    expect($result)->toHaveKey('products');
    
    expect($result['count'])->toBe(2);
    expect($result['max_pages'])->toBe(1);
    expect($result['products'])->toBeArray();
    expect($result['products'])->toHaveCount(2);
});

it('ensures empty fields are strings not null for backward compatibility', function () {
    $productWithNulls = [
        'title' => null,
        'subtitle' => null,
        'parent_id' => 0,
        'page_unique' => 123,
        'current_price' => null,
        'old_price' => null,
        'availability' => null,
        'category_name' => null,
        'image_links' => null,
        'image_link' => null,
        'page_url' => null,
        'short_desc' => null,
        'spec' => null,
        'date' => null,
        'registry' => null,
        'guarantee' => null
    ];
    
    $resource = new ProductResource($productWithNulls);
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
});

it('preserves URL encoding in variation parameters', function () {
    $productWithEncodedUrl = [
        'title' => 'Variable Product',
        'subtitle' => '',
        'parent_id' => 100,
        'page_unique' => 456,
        'current_price' => '199.99',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Variables',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product/variable/?attribute_color=red%20blue&attribute_size=large%2Fmedium&attribute_material=cotton%26polyester',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productWithEncodedUrl);
    $result = $resource->toArray();
    
    // URL encoding should be preserved exactly
    expect($result['page_url'])->toBe('https://example.com/product/variable/?attribute_color=red%20blue&attribute_size=large%2Fmedium&attribute_material=cotton%26polyester');
    
    // Verify specific encoded characters are preserved
    expect($result['page_url'])->toContain('%20'); // space
    expect($result['page_url'])->toContain('%2F'); // forward slash
    expect($result['page_url'])->toContain('%26'); // ampersand
});

it('extracts registry and guarantee from specifications correctly', function () {
    $productWithSpecExtraction = [
        'title' => 'Product with Extractable Fields',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 789,
        'current_price' => '299.99',
        'old_price' => '399.99',
        'availability' => 'instock',
        'category_name' => 'Premium',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/premium',
        'short_desc' => '',
        'spec' => [['رنگ' => 'طلایی', 'رجیستری' => 'دارد', 'گارانتی' => '24 ماه', 'برند' => 'پریمیوم']],
        'date' => [],
        'registry' => 'دارد', // Should be extracted from spec
        'guarantee' => '24 ماه' // Should be extracted from spec
    ];
    
    $resource = new ProductResource($productWithSpecExtraction);
    $result = $resource->toArray();
    
    // Registry and guarantee should be extracted from specifications
    expect($result['registry'])->toBe('دارد');
    expect($result['guarantee'])->toBe('24 ماه');
    
    // Spec should remain intact
    expect($result['spec'])->toBe([['رنگ' => 'طلایی', 'رجیستری' => 'دارد', 'گارانتی' => '24 ماه', 'برند' => 'پریمیوم']]);
});

it('maintains price format as strings for backward compatibility', function () {
    $productWithNumericPrices = [
        'title' => 'Product with Numeric Prices',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 999,
        'current_price' => 99.99, // Numeric input
        'old_price' => 149.99, // Numeric input
        'availability' => 'instock',
        'category_name' => 'Test',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/test',
        'short_desc' => '',
        'spec' => [],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productWithNumericPrices);
    $result = $resource->toArray();
    
    // Prices should be converted to strings for backward compatibility
    expect($result['current_price'])->toBe('99.99');
    expect($result['old_price'])->toBe('149.99');
    expect($result['current_price'])->toBeString();
    expect($result['old_price'])->toBeString();
});

it('handles variation relationships correctly', function () {
    $variationProduct = [
        'title' => 'Product Variation',
        'subtitle' => '',
        'parent_id' => 500, // Non-zero indicates this is a variation
        'page_unique' => 501,
        'current_price' => '79.99',
        'old_price' => '99.99',
        'availability' => 'instock',
        'category_name' => 'Variations',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product/variation/?attribute_color=blue',
        'short_desc' => '',
        'spec' => [['color' => 'blue', 'size' => 'medium']],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($variationProduct);
    $result = $resource->toArray();
    
    // Parent ID should correctly reference the main product
    expect($result['parent_id'])->toBe(500);
    expect($result['parent_id'])->toBeInt();
    
    // Variation should have its own unique ID
    expect($result['page_unique'])->toBe(501);
});

it('maintains date structure with exact format', function () {
    $productWithDate = [
        'title' => 'Product with Date',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 888,
        'current_price' => '55.00',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Dated',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/dated',
        'short_desc' => '',
        'spec' => [],
        'date' => [
            'date' => '2023-12-01 15:30:45.123456',
            'timezone_type' => 3,
            'timezone' => 'UTC'
        ],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productWithDate);
    $result = $resource->toArray();
    
    // Date should maintain exact structure
    expect($result['date'])->toHaveKey('date');
    expect($result['date'])->toHaveKey('timezone_type');
    expect($result['date'])->toHaveKey('timezone');
    
    expect($result['date']['date'])->toBe('2023-12-01 15:30:45.123456');
    expect($result['date']['timezone_type'])->toBe(3);
    expect($result['date']['timezone'])->toBe('UTC');
});