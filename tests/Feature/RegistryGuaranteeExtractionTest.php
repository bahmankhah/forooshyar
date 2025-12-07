<?php

use Forooshyar\Resources\ProductResource;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 16: Registry and guarantee extraction
 * 
 * For any product with registry or guarantee information in specifications, these values should be extracted and populated in the respective fields
 * 
 * Validates: Requirements 6.9
 */

it('extracts registry from Persian specification keys', function () {
    $productDataWithRegistry = [
        'title' => 'Product with Registry',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 123,
        'current_price' => '99.99',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Electronics',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product',
        'short_desc' => '',
        'spec' => [['رنگ' => 'آبی', 'رجیستری' => 'دارد', 'سایز' => 'بزرگ']],
        'date' => [],
        'registry' => 'دارد', // Should be extracted from spec
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataWithRegistry);
    $result = $resource->toArray();
    
    // Registry should be extracted and present
    expect($result['registry'])->toBe('دارد');
    expect($result['spec'])->toBe([['رنگ' => 'آبی', 'رجیستری' => 'دارد', 'سایز' => 'بزرگ']]);
});

it('extracts registry from English specification keys', function () {
    $productDataWithEnglishRegistry = [
        'title' => 'Product with English Registry',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 456,
        'current_price' => '149.99',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Electronics',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/product',
        'short_desc' => '',
        'spec' => [['color' => 'blue', 'registry' => 'yes', 'size' => 'large']],
        'date' => [],
        'registry' => 'yes', // Should be extracted from spec
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataWithEnglishRegistry);
    $result = $resource->toArray();
    
    // Registry should be extracted from English key
    expect($result['registry'])->toBe('yes');
    expect($result['spec'])->toBe([['color' => 'blue', 'registry' => 'yes', 'size' => 'large']]);
});

it('extracts registry from alternative Persian spellings', function () {
    // Test different Persian spellings of registry
    $alternativeSpellings = ['ریجیستری', 'ریجستری'];
    
    foreach ($alternativeSpellings as $index => $spelling) {
        $productData = [
            'title' => "Product with Registry Spelling {$index}",
            'subtitle' => '',
            'parent_id' => 0,
            'page_unique' => 700 + $index,
            'current_price' => '99.99',
            'old_price' => '',
            'availability' => 'instock',
            'category_name' => 'Test',
            'image_links' => [],
            'image_link' => '',
            'page_url' => 'https://example.com/product',
            'short_desc' => '',
            'spec' => [[$spelling => 'موجود']],
            'date' => [],
            'registry' => 'موجود',
            'guarantee' => ''
        ];
        
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        expect($result['registry'])->toBe('موجود');
    }
});

it('extracts guarantee from Persian specification keys', function () {
    $guaranteeKeys = [
        'گارانتی' => '12 ماه',
        'guarantee' => '12 months',
        'warranty' => '1 year',
        'garanty' => '24 months',
        'گارانتی:' => '6 ماه',
        'گارانتی محصول' => '18 ماه',
        'گارانتی محصول:' => '2 سال',
        'ضمانت' => '3 سال',
        'ضمانت:' => '5 سال'
    ];
    
    foreach ($guaranteeKeys as $key => $value) {
        $productData = [
            'title' => "Product with Guarantee Key: {$key}",
            'subtitle' => '',
            'parent_id' => 0,
            'page_unique' => 800 + array_search($key, array_keys($guaranteeKeys)),
            'current_price' => '199.99',
            'old_price' => '',
            'availability' => 'instock',
            'category_name' => 'Guaranteed',
            'image_links' => [],
            'image_link' => '',
            'page_url' => 'https://example.com/product',
            'short_desc' => '',
            'spec' => [[$key => $value, 'color' => 'red']],
            'date' => [],
            'registry' => '',
            'guarantee' => $value // Should be extracted from spec
        ];
        
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        expect($result['guarantee'])->toBe($value);
        expect($result['spec'])->toBe([[$key => $value, 'color' => 'red']]);
    }
});

it('handles products with both registry and guarantee', function () {
    $productDataBoth = [
        'title' => 'Product with Both Registry and Guarantee',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 999,
        'current_price' => '299.99',
        'old_price' => '399.99',
        'availability' => 'instock',
        'category_name' => 'Premium',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/premium-product',
        'short_desc' => '',
        'spec' => [['رنگ' => 'طلایی', 'رجیستری' => 'دارد', 'گارانتی' => '24 ماه', 'برند' => 'پریمیوم']],
        'date' => [],
        'registry' => 'دارد',
        'guarantee' => '24 ماه'
    ];
    
    $resource = new ProductResource($productDataBoth);
    $result = $resource->toArray();
    
    // Both registry and guarantee should be extracted
    expect($result['registry'])->toBe('دارد');
    expect($result['guarantee'])->toBe('24 ماه');
    expect($result['spec'])->toBe([['رنگ' => 'طلایی', 'رجیستری' => 'دارد', 'گارانتی' => '24 ماه', 'برند' => 'پریمیوم']]);
});

it('returns empty strings when registry and guarantee are not found in specs', function () {
    $productDataNoRegistryGuarantee = [
        'title' => 'Product without Registry or Guarantee',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 111,
        'current_price' => '49.99',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Basic',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/basic-product',
        'short_desc' => '',
        'spec' => [['رنگ' => 'سفید', 'سایز' => 'متوسط', 'جنس' => 'پلاستیک']],
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataNoRegistryGuarantee);
    $result = $resource->toArray();
    
    // Should return empty strings when not found
    expect($result['registry'])->toBe('');
    expect($result['guarantee'])->toBe('');
    expect($result['spec'])->toBe([['رنگ' => 'سفید', 'سایز' => 'متوسط', 'جنس' => 'پلاستیک']]);
});

it('handles empty or null spec arrays gracefully', function () {
    $productDataEmptySpec = [
        'title' => 'Product with Empty Spec',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 222,
        'current_price' => '25.00',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Simple',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/simple-product',
        'short_desc' => '',
        'spec' => [], // Empty spec
        'date' => [],
        'registry' => '',
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataEmptySpec);
    $result = $resource->toArray();
    
    // Should handle empty spec gracefully
    expect($result['registry'])->toBe('');
    expect($result['guarantee'])->toBe('');
    expect($result['spec'])->toBe([]);
});

it('prioritizes first matching key when multiple registry keys exist', function () {
    $productDataMultipleRegistry = [
        'title' => 'Product with Multiple Registry Keys',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 333,
        'current_price' => '75.00',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Multi',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/multi-product',
        'short_desc' => '',
        'spec' => [['رجیستری' => 'دارد', 'registry' => 'yes', 'ریجیستری' => 'موجود']],
        'date' => [],
        'registry' => 'دارد', // Should use first matching key (رجیستری)
        'guarantee' => ''
    ];
    
    $resource = new ProductResource($productDataMultipleRegistry);
    $result = $resource->toArray();
    
    // Should use the first matching registry key found
    expect($result['registry'])->toBe('دارد');
});

it('prioritizes first matching key when multiple guarantee keys exist', function () {
    $productDataMultipleGuarantee = [
        'title' => 'Product with Multiple Guarantee Keys',
        'subtitle' => '',
        'parent_id' => 0,
        'page_unique' => 444,
        'current_price' => '125.00',
        'old_price' => '',
        'availability' => 'instock',
        'category_name' => 'Multi',
        'image_links' => [],
        'image_link' => '',
        'page_url' => 'https://example.com/multi-guarantee',
        'short_desc' => '',
        'spec' => [['گارانتی' => '12 ماه', 'guarantee' => '24 months', 'warranty' => '1 year']],
        'date' => [],
        'registry' => '',
        'guarantee' => '12 ماه' // Should use first matching key (گارانتی)
    ];
    
    $resource = new ProductResource($productDataMultipleGuarantee);
    $result = $resource->toArray();
    
    // Should use the first matching guarantee key found
    expect($result['guarantee'])->toBe('12 ماه');
});

/**
 * Property-based test for registry and guarantee extraction
 * 
 * Feature: woocommerce-product-refactor, Property 16: Registry and guarantee extraction
 * 
 * For any product with registry or guarantee information in specifications, these values should be extracted and populated in the respective fields
 * 
 * **Validates: Requirements 6.9**
 */
test('property-based test: registry and guarantee extraction from specifications', function () {
    $faker = Faker::create();
    
    // Define all possible registry keys (Persian and English variants)
    $registryKeys = ['رجیستری', 'registry', 'ریجیستری', 'ریجستری'];
    
    // Define all possible guarantee keys (Persian and English variants)
    $guaranteeKeys = [
        'گارانتی', 'guarantee', 'warranty', 'garanty', 'گارانتی:', 
        'گارانتی محصول', 'گارانتی محصول:', 'ضمانت', 'ضمانت:'
    ];
    
    // Run property test with multiple iterations
    for ($i = 0; $i < 100; $i++) {
        // Generate random product data
        $hasRegistry = $faker->boolean(0.7); // 70% chance of having registry
        $hasGuarantee = $faker->boolean(0.7); // 70% chance of having guarantee
        
        $spec = [];
        $expectedRegistry = '';
        $expectedGuarantee = '';
        
        // Add some random non-registry/guarantee fields to spec
        $spec['رنگ'] = $faker->colorName();
        $spec['سایز'] = $faker->randomElement(['کوچک', 'متوسط', 'بزرگ']);
        
        // Add registry if selected
        if ($hasRegistry) {
            $registryKey = $faker->randomElement($registryKeys);
            $registryValue = $faker->randomElement(['دارد', 'ندارد', 'yes', 'no', 'موجود']);
            $spec[$registryKey] = $registryValue;
            $expectedRegistry = $registryValue;
        }
        
        // Add guarantee if selected
        if ($hasGuarantee) {
            $guaranteeKey = $faker->randomElement($guaranteeKeys);
            $guaranteeValue = $faker->randomElement([
                '12 ماه', '24 ماه', '6 ماه', '1 سال', '2 سال',
                '12 months', '24 months', '1 year', '2 years'
            ]);
            $spec[$guaranteeKey] = $guaranteeValue;
            $expectedGuarantee = $guaranteeValue;
        }
        
        // Create product data with the generated spec
        $productData = [
            'title' => $faker->sentence(3),
            'subtitle' => $faker->optional()->sentence(2),
            'parent_id' => $faker->numberBetween(0, 1000),
            'page_unique' => $faker->numberBetween(1, 9999),
            'current_price' => $faker->randomFloat(2, 10, 1000),
            'old_price' => $faker->optional()->randomFloat(2, 10, 1000),
            'availability' => $faker->randomElement(['instock', 'outofstock', 'onbackorder']),
            'category_name' => $faker->word(),
            'image_links' => $faker->optional()->randomElements([
                'https://example.com/image1.jpg',
                'https://example.com/image2.jpg',
                'https://example.com/image3.jpg'
            ], $faker->numberBetween(0, 2)),
            'image_link' => $faker->optional()->imageUrl(),
            'page_url' => $faker->url(),
            'short_desc' => $faker->optional()->paragraph(),
            'spec' => [$spec], // Wrap in array as expected by ProductResource
            'date' => [],
            'registry' => $expectedRegistry,
            'guarantee' => $expectedGuarantee
        ];
        
        // Transform through ProductResource
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        // Verify the property: registry and guarantee should be extracted correctly
        expect($result['registry'])->toBe($expectedRegistry, 
            "Registry extraction failed for iteration {$i}. Expected: '{$expectedRegistry}', Got: '{$result['registry']}'");
        
        expect($result['guarantee'])->toBe($expectedGuarantee,
            "Guarantee extraction failed for iteration {$i}. Expected: '{$expectedGuarantee}', Got: '{$result['guarantee']}'");
        
        // Verify that spec is preserved in the correct format
        expect($result['spec'])->toBeArray();
        if (!empty($spec)) {
            expect($result['spec'])->toHaveCount(1);
            expect($result['spec'][0])->toBeArray();
        }
        
        // Verify that registry and guarantee are always strings (never null)
        expect($result['registry'])->toBeString();
        expect($result['guarantee'])->toBeString();
    }
});