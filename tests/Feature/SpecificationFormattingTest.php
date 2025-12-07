<?php

use Tests\TestCase;
use Forooshyar\Resources\ProductResource;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 9: Specification format consistency
 * Validates: Requirements 6.3, 14.4
 */

describe('Specification Formatting', function () {
    
    function generateProductDataWithSpec($spec) {
        $faker = Faker::create();
        
        return [
            'title' => $faker->words(3, true),
            'subtitle' => $faker->words(2, true),
            'parent_id' => $faker->numberBetween(0, 1000),
            'page_unique' => $faker->numberBetween(1, 10000),
            'current_price' => (string) $faker->randomFloat(2, 10, 1000),
            'old_price' => (string) $faker->randomFloat(2, 15, 1200),
            'availability' => $faker->randomElement(['instock', 'outofstock', 'onbackorder']),
            'category_name' => $faker->words(2, true),
            'image_links' => [],
            'image_link' => '',
            'page_url' => $faker->url(),
            'short_desc' => $faker->sentence(),
            'spec' => $spec,
            'date' => new DateTime(),
            'registry' => '',
            'guarantee' => ''
        ];
    }
    
    test('property 9: specification format consistency - for any product response that includes specifications, the spec field should be formatted as an array containing a single object with attribute key-value pairs', function () {
        // Property: For any product response that includes specifications, the spec field should be 
        // formatted as an array containing a single object with attribute key-value pairs
        
        $faker = Faker::create();
        
        // Test various spec input formats
        $specTestCases = [
            // Object input (should be wrapped in array)
            [
                'input' => (object) [
                    'color' => 'Red',
                    'size' => 'Large',
                    'material' => 'Cotton'
                ],
                'description' => 'stdClass object'
            ],
            
            // Associative array input (should be wrapped in array)
            [
                'input' => [
                    'brand' => 'Nike',
                    'model' => 'Air Max',
                    'year' => '2023'
                ],
                'description' => 'associative array'
            ],
            
            // Already formatted as array with single object (should remain as-is)
            [
                'input' => [
                    [
                        'weight' => '1.5kg',
                        'dimensions' => '30x20x10cm',
                        'warranty' => '2 years'
                    ]
                ],
                'description' => 'already formatted array'
            ],
            
            // Empty cases
            [
                'input' => [],
                'description' => 'empty array'
            ],
            
            [
                'input' => null,
                'description' => 'null value'
            ],
            
            [
                'input' => '',
                'description' => 'empty string'
            ]
        ];
        
        foreach ($specTestCases as $testCase) {
            $productData = generateProductDataWithSpec($testCase['input']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Verify spec is always an array
            expect($result['spec'])->toBeArray();
            
            if (empty($testCase['input'])) {
                // Empty inputs should result in empty array
                expect($result['spec'])->toBeEmpty();
            } else {
                if (is_array($testCase['input']) && count($testCase['input']) === 1 && is_array($testCase['input'][0])) {
                    // Already formatted correctly - should remain as-is
                    expect($result['spec'])->toBe($testCase['input']);
                } else {
                    // Should be wrapped in array with single object
                    expect($result['spec'])->toHaveCount(1);
                    expect($result['spec'][0])->toBeArray();
                    
                    // Content should match the original data
                    $expectedContent = (array) $testCase['input'];
                    expect($result['spec'][0])->toBe($expectedContent);
                }
            }
        }
        
        // Test with random generated specifications
        for ($i = 0; $i < 50; $i++) {
            $specData = [];
            $attributeCount = $faker->numberBetween(1, 8);
            
            for ($j = 0; $j < $attributeCount; $j++) {
                $key = $faker->randomElement([
                    'color', 'size', 'material', 'brand', 'model', 'weight',
                    'dimensions', 'warranty', 'country', 'type', 'style'
                ]);
                $value = $faker->randomElement([
                    $faker->colorName(),
                    $faker->word(),
                    $faker->numberBetween(1, 100) . 'cm',
                    $faker->company(),
                    $faker->bothify('Model-###'),
                    $faker->randomFloat(2, 0.1, 50) . 'kg'
                ]);
                
                $specData[$key] = $value;
            }
            
            // Test both object and array formats
            $formats = [
                (object) $specData,  // stdClass
                $specData            // associative array
            ];
            
            foreach ($formats as $format) {
                $productData = generateProductDataWithSpec($format);
                $resource = new ProductResource($productData);
                $result = $resource->toArray();
                
                expect($result['spec'])->toBeArray();
                expect($result['spec'])->toHaveCount(1);
                expect($result['spec'][0])->toBeArray();
                expect($result['spec'][0])->toBe($specData);
            }
        }
        
    })->repeat(100);
    
    test('specification formatting handles complex nested data correctly', function () {
        $faker = Faker::create();
        
        // Test with complex specification data
        $complexSpec = [
            'basic_info' => 'Product Info',
            'technical_specs' => 'Advanced Features',
            'measurements' => '100x50x25cm',
            'materials' => 'Steel, Plastic, Rubber',
            'certifications' => 'CE, RoHS, FCC',
            'compatibility' => 'Universal'
        ];
        
        $productData = generateProductDataWithSpec($complexSpec);
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        expect($result['spec'])->toBeArray();
        expect($result['spec'])->toHaveCount(1);
        expect($result['spec'][0])->toBe($complexSpec);
        
        // Verify all keys and values are preserved
        foreach ($complexSpec as $key => $value) {
            expect($result['spec'][0])->toHaveKey($key);
            expect($result['spec'][0][$key])->toBe($value);
        }
        
    })->repeat(30);
    
    test('specification formatting maintains backward compatibility', function () {
        $faker = Faker::create();
        
        // Test the exact format expected by existing API consumers
        $originalSpec = [
            'رنگ' => 'قرمز',
            'سایز' => 'بزرگ',
            'جنس' => 'پنبه',
            'برند' => 'نایک'
        ];
        
        $productData = generateProductDataWithSpec($originalSpec);
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        // Verify the exact structure that third-party integrations expect
        expect($result['spec'])->toBeArray();
        expect($result['spec'])->toHaveCount(1);
        expect($result['spec'][0])->toBeArray();
        expect($result['spec'][0])->toBe($originalSpec);
        
        // Verify Persian keys are preserved correctly
        expect($result['spec'][0])->toHaveKey('رنگ');
        expect($result['spec'][0])->toHaveKey('سایز');
        expect($result['spec'][0]['رنگ'])->toBe('قرمز');
        expect($result['spec'][0]['سایز'])->toBe('بزرگ');
        
        // Test with already formatted spec (should not double-wrap)
        $alreadyFormatted = [$originalSpec];
        $productData2 = generateProductDataWithSpec($alreadyFormatted);
        $resource2 = new ProductResource($productData2);
        $result2 = $resource2->toArray();
        
        expect($result2['spec'])->toBe($alreadyFormatted);
        expect($result2['spec'])->toHaveCount(1);
        expect($result2['spec'][0])->toBe($originalSpec);
        
    });
    
});