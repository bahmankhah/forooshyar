<?php

use Tests\TestCase;
use Forooshyar\Resources\ProductResource;
use Forooshyar\Resources\ProductCollectionResource;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 1: Response transformation consistency
 * Validates: Requirements 1.1, 1.2, 1.3, 14.1
 */

describe('Response Transformation', function () {
    
    function generateProductData() {
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
            'image_links' => $faker->randomElements([
                'https://example.com/image1.jpg',
                'https://example.com/image2.jpg',
                'https://example.com/image3.jpg'
            ], $faker->numberBetween(1, 3)),
            'image_link' => 'https://example.com/main-image.jpg',
            'page_url' => $faker->url(),
            'short_desc' => $faker->sentence(),
            'spec' => [
                'color' => $faker->colorName(),
                'size' => $faker->randomElement(['S', 'M', 'L', 'XL']),
                'material' => $faker->word()
            ],
            'date' => new DateTime(),
            'registry' => $faker->optional()->word(),
            'guarantee' => $faker->optional()->sentence()
        ];
    }
    
    function generateCollectionData() {
        $faker = Faker::create();
        $productCount = $faker->numberBetween(1, 10);
        
        $products = [];
        for ($i = 0; $i < $productCount; $i++) {
            $products[] = generateProductData();
        }
        
        return [
            'count' => $faker->numberBetween($productCount, $productCount * 10),
            'max_pages' => $faker->numberBetween(1, 20),
            'products' => $products
        ];
    }
    
    test('property 1: response transformation consistency - for any API response, all product data should be transformed through JsonResource classes and maintain the exact structure', function () {
        // Property: For any API response, all product data should be transformed through JsonResource classes 
        // and maintain the exact structure with count, max_pages, and products array
        
        $faker = Faker::create();
        
        // Test single product transformation
        $productData = generateProductData();
        $resource = new ProductResource($productData);
        $transformed = $resource->toArray();
        
        // Verify all expected fields are present
        $expectedFields = [
            'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
            'old_price', 'availability', 'category_name', 'image_links',
            'image_link', 'page_url', 'short_desc', 'spec', 'date',
            'registry', 'guarantee'
        ];
        
        foreach ($expectedFields as $field) {
            expect($transformed)->toHaveKey($field);
        }
        
        // Verify data types are maintained correctly
        expect($transformed['title'])->toBeString();
        expect($transformed['subtitle'])->toBeString();
        expect($transformed['parent_id'])->toBeInt();
        expect($transformed['page_unique'])->toBeInt();
        expect($transformed['current_price'])->toBeString();
        expect($transformed['old_price'])->toBeString();
        expect($transformed['availability'])->toBeString();
        expect($transformed['category_name'])->toBeString();
        expect($transformed['image_links'])->toBeArray();
        expect($transformed['image_link'])->toBeString();
        expect($transformed['page_url'])->toBeString();
        expect($transformed['short_desc'])->toBeString();
        expect($transformed['spec'])->toBeArray();
        expect($transformed['date'])->toBeArray();
        expect($transformed['registry'])->toBeString();
        expect($transformed['guarantee'])->toBeString();
        
        // Test collection transformation
        $collectionData = generateCollectionData();
        $collectionResource = new ProductCollectionResource($collectionData);
        $collectionTransformed = $collectionResource->toArray();
        
        // Verify collection structure
        expect($collectionTransformed)->toHaveKey('count');
        expect($collectionTransformed)->toHaveKey('max_pages');
        expect($collectionTransformed)->toHaveKey('products');
        
        expect($collectionTransformed['count'])->toBeInt();
        expect($collectionTransformed['max_pages'])->toBeInt();
        expect($collectionTransformed['products'])->toBeArray();
        
        // Verify each product in collection is properly transformed
        foreach ($collectionTransformed['products'] as $product) {
            foreach ($expectedFields as $field) {
                expect($product)->toHaveKey($field);
            }
        }
        
        // Test static collection method
        $products = array_map(function() { return generateProductData(); }, range(1, $faker->numberBetween(2, 5)));
        $staticCollection = ProductResource::collection($products);
        
        expect($staticCollection)->toBeArray();
        expect($staticCollection)->toHaveCount(count($products));
        
        foreach ($staticCollection as $transformedProduct) {
            foreach ($expectedFields as $field) {
                expect($transformedProduct)->toHaveKey($field);
            }
        }
        
    })->repeat(100);
    
    test('collection resource maintains exact backward compatibility structure', function () {
        $faker = Faker::create();
        
        // Generate test data
        $products = array_map(function() { return generateProductData(); }, range(1, $faker->numberBetween(1, 5)));
        $count = $faker->numberBetween(count($products), count($products) * 10);
        $maxPages = $faker->numberBetween(1, 20);
        
        // Test using make method
        $resource = ProductCollectionResource::make($products, $count, $maxPages);
        $result = $resource->toArray();
        
        // Verify exact structure
        expect($result)->toHaveKeys(['count', 'max_pages', 'products']);
        expect($result['count'])->toBe($count);
        expect($result['max_pages'])->toBe($maxPages);
        expect($result['products'])->toHaveCount(count($products));
        
        // Test using fromArray method
        $arrayData = [
            'count' => $count,
            'max_pages' => $maxPages,
            'products' => $products
        ];
        
        $resource2 = ProductCollectionResource::fromArray($arrayData);
        $result2 = $resource2->toArray();
        
        expect($result2)->toEqual($result);
        
    })->repeat(50);
    
    test('product resource handles missing fields gracefully', function () {
        $faker = Faker::create();
        
        // Test with minimal data
        $minimalData = [
            'title' => $faker->words(3, true),
            'page_unique' => $faker->numberBetween(1, 1000)
        ];
        
        $resource = new ProductResource($minimalData);
        $result = $resource->toArray();
        
        // Should include all fields for backward compatibility (Requirement 14.8)
        // Missing fields should be empty strings or appropriate defaults
        expect($result)->toHaveKey('title');
        expect($result)->toHaveKey('page_unique');
        expect($result)->toHaveKey('subtitle');
        expect($result)->toHaveKey('current_price');
        
        // Verify provided fields have correct values
        expect($result['title'])->toBe($minimalData['title']);
        expect($result['page_unique'])->toBe($minimalData['page_unique']);
        
        // Verify missing fields have appropriate defaults
        expect($result['subtitle'])->toBe('');
        expect($result['current_price'])->toBe('');
        expect($result['registry'])->toBe('');
        expect($result['guarantee'])->toBe('');
        
        // Test with stdClass object
        $objectData = (object) $minimalData;
        $resource2 = new ProductResource($objectData);
        $result2 = $resource2->toArray();
        
        expect($result2)->toEqual($result);
        
    })->repeat(30);
    
    test('price formatting maintains string type for backward compatibility', function () {
        $faker = Faker::create();
        
        $testCases = [
            ['current_price' => 100, 'old_price' => 150],
            ['current_price' => '100.50', 'old_price' => '150.75'],
            ['current_price' => 0, 'old_price' => 0],
            ['current_price' => '', 'old_price' => ''],
            ['current_price' => null, 'old_price' => null]
        ];
        
        foreach ($testCases as $priceData) {
            $productData = array_merge(generateProductData(), $priceData);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            if (isset($result['current_price'])) {
                expect($result['current_price'])->toBeString();
            }
            
            if (isset($result['old_price'])) {
                expect($result['old_price'])->toBeString();
            }
        }
        
    });
    
});