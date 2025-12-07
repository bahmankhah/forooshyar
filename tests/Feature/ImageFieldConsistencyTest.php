<?php

use Tests\TestCase;
use Forooshyar\Resources\ProductResource;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 10: Image field consistency
 * Validates: Requirements 6.5, 14.6
 */

describe('Image Field Consistency', function () {
    
    function generateProductDataWithImages($imageLink, $imageLinks) {
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
            'image_links' => $imageLinks,
            'image_link' => $imageLink,
            'page_url' => $faker->url(),
            'short_desc' => $faker->sentence(),
            'spec' => [],
            'date' => new DateTime(),
            'registry' => '',
            'guarantee' => ''
        ];
    }
    
    test('property 10: image field consistency - for any product response, image_link should be a single string and image_links should be an array of strings', function () {
        // Property: For any product response, image_link should be a single string and image_links should be an array of strings
        
        $faker = Faker::create();
        
        // Test various image input combinations
        $imageTestCases = [
            // Standard cases
            [
                'image_link' => 'https://example.com/main-image.jpg',
                'image_links' => [
                    'https://example.com/image1.jpg',
                    'https://example.com/image2.jpg',
                    'https://example.com/image3.jpg'
                ],
                'description' => 'standard multiple images'
            ],
            
            // Single image case
            [
                'image_link' => 'https://example.com/single-image.jpg',
                'image_links' => ['https://example.com/single-image.jpg'],
                'description' => 'single image'
            ],
            
            // Empty cases
            [
                'image_link' => '',
                'image_links' => [],
                'description' => 'no images'
            ],
            
            // Null cases
            [
                'image_link' => null,
                'image_links' => null,
                'description' => 'null values'
            ],
            
            // Mixed valid/invalid URLs
            [
                'image_link' => 'https://cdn.example.com/products/main.png',
                'image_links' => [
                    'https://cdn.example.com/products/gallery1.png',
                    'https://cdn.example.com/products/gallery2.jpg',
                    'https://static.example.com/thumbs/thumb.webp'
                ],
                'description' => 'mixed image formats'
            ],
            
            // Edge case: non-array image_links input
            [
                'image_link' => 'https://example.com/main.jpg',
                'image_links' => 'https://example.com/single.jpg',  // String instead of array
                'description' => 'string image_links input'
            ]
        ];
        
        foreach ($imageTestCases as $testCase) {
            $productData = generateProductDataWithImages($testCase['image_link'], $testCase['image_links']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Verify image_link is always a string
            expect($result['image_link'])->toBeString();
            
            // Verify image_links is always an array
            expect($result['image_links'])->toBeArray();
            
            // Verify all elements in image_links are strings
            foreach ($result['image_links'] as $imageUrl) {
                expect($imageUrl)->toBeString();
            }
            
            // Verify content correctness
            if ($testCase['image_link'] === null) {
                expect($result['image_link'])->toBe('');
            } else {
                expect($result['image_link'])->toBe((string) $testCase['image_link']);
            }
            
            if ($testCase['image_links'] === null || !is_array($testCase['image_links'])) {
                expect($result['image_links'])->toBe([]);
            } else {
                // All elements should be converted to strings
                $expectedLinks = array_map('strval', $testCase['image_links']);
                expect($result['image_links'])->toBe($expectedLinks);
            }
        }
        
        // Test with random generated image data
        for ($i = 0; $i < 50; $i++) {
            $imageCount = $faker->numberBetween(0, 10);
            $imageLinks = [];
            
            for ($j = 0; $j < $imageCount; $j++) {
                $imageLinks[] = $faker->imageUrl(800, 600, 'products', true);
            }
            
            $mainImage = $imageCount > 0 ? $faker->randomElement($imageLinks) : '';
            
            $productData = generateProductDataWithImages($mainImage, $imageLinks);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Always verify types
            expect($result['image_link'])->toBeString();
            expect($result['image_links'])->toBeArray();
            
            // Verify content
            expect($result['image_link'])->toBe($mainImage);
            expect($result['image_links'])->toBe($imageLinks);
            
            // Verify all array elements are strings
            foreach ($result['image_links'] as $imageUrl) {
                expect($imageUrl)->toBeString();
            }
        }
        
    })->repeat(100);
    
    test('image field formatting handles edge cases correctly', function () {
        $faker = Faker::create();
        
        $edgeCases = [
            // Empty string vs null
            [
                'image_link' => '',
                'image_links' => [],
                'expected_link' => '',
                'expected_links' => []
            ],
            
            // Null values
            [
                'image_link' => null,
                'image_links' => null,
                'expected_link' => '',
                'expected_links' => []
            ],
            
            // Non-array image_links
            [
                'image_link' => 'https://example.com/main.jpg',
                'image_links' => 'not-an-array',
                'expected_link' => 'https://example.com/main.jpg',
                'expected_links' => []
            ],
            
            // Array with mixed types (should convert to strings)
            [
                'image_link' => 'https://example.com/main.jpg',
                'image_links' => [
                    'https://example.com/image1.jpg',
                    123,  // Number
                    null, // Null
                    'https://example.com/image2.jpg'
                ],
                'expected_link' => 'https://example.com/main.jpg',
                'expected_links' => [
                    'https://example.com/image1.jpg',
                    '123',
                    '',
                    'https://example.com/image2.jpg'
                ]
            ]
        ];
        
        foreach ($edgeCases as $case) {
            $productData = generateProductDataWithImages($case['image_link'], $case['image_links']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            expect($result['image_link'])->toBe($case['expected_link']);
            expect($result['image_links'])->toBe($case['expected_links']);
            expect($result['image_link'])->toBeString();
            expect($result['image_links'])->toBeArray();
        }
        
    })->repeat(30);
    
    test('image field formatting maintains backward compatibility', function () {
        $faker = Faker::create();
        
        // Test the exact format expected by existing API consumers
        $mainImage = 'https://shop.example.com/products/main-product-image.jpg';
        $galleryImages = [
            'https://shop.example.com/products/gallery-1.jpg',
            'https://shop.example.com/products/gallery-2.jpg',
            'https://shop.example.com/products/gallery-3.jpg'
        ];
        
        $productData = generateProductDataWithImages($mainImage, $galleryImages);
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        // Verify the exact structure that third-party integrations expect
        expect($result['image_link'])->toBe($mainImage);
        expect($result['image_links'])->toBe($galleryImages);
        
        // Verify types are exactly what's expected
        expect($result['image_link'])->toBeString();
        expect($result['image_links'])->toBeArray();
        
        // Verify these are not other types that could break existing integrations
        expect($result['image_link'])->not->toBeArray();
        expect($result['image_link'])->not->toBeNull();
        expect($result['image_links'])->not->toBeString();
        expect($result['image_links'])->not->toBeNull();
        
        // Verify array elements are all strings
        foreach ($result['image_links'] as $imageUrl) {
            expect($imageUrl)->toBeString();
            expect($imageUrl)->not->toBeArray();
            expect($imageUrl)->not->toBeNull();
        }
        
        // Test with empty images (should still maintain correct types)
        $productData2 = generateProductDataWithImages('', []);
        $resource2 = new ProductResource($productData2);
        $result2 = $resource2->toArray();
        
        expect($result2['image_link'])->toBe('');
        expect($result2['image_links'])->toBe([]);
        expect($result2['image_link'])->toBeString();
        expect($result2['image_links'])->toBeArray();
        
    });
    
});