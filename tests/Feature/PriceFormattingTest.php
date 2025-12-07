<?php

use Tests\TestCase;
use Forooshyar\Resources\ProductResource;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 8: Price format consistency
 * Validates: Requirements 6.6, 14.3
 */

describe('Price Formatting', function () {
    
    function generateProductDataWithPrices($currentPrice, $oldPrice) {
        $faker = Faker::create();
        
        return [
            'title' => $faker->words(3, true),
            'subtitle' => $faker->words(2, true),
            'parent_id' => $faker->numberBetween(0, 1000),
            'page_unique' => $faker->numberBetween(1, 10000),
            'current_price' => $currentPrice,
            'old_price' => $oldPrice,
            'availability' => $faker->randomElement(['instock', 'outofstock', 'onbackorder']),
            'category_name' => $faker->words(2, true),
            'image_links' => [],
            'image_link' => '',
            'page_url' => $faker->url(),
            'short_desc' => $faker->sentence(),
            'spec' => [],
            'date' => new DateTime(),
            'registry' => '',
            'guarantee' => ''
        ];
    }
    
    test('property 8: price format consistency - for any product response, current_price and old_price should always be formatted as strings, never as numbers', function () {
        // Property: For any product response, current_price and old_price should always be formatted as strings, never as numbers
        
        $faker = Faker::create();
        
        // Test various price input types
        $priceTestCases = [
            // Numeric values
            ['current' => 100, 'old' => 150],
            ['current' => 99.99, 'old' => 129.99],
            ['current' => 0, 'old' => 0],
            ['current' => 1000000, 'old' => 1200000],
            
            // String values
            ['current' => '100', 'old' => '150'],
            ['current' => '99.99', 'old' => '129.99'],
            ['current' => '0', 'old' => '0'],
            ['current' => '1000.50', 'old' => '1200.75'],
            
            // Edge cases
            ['current' => '', 'old' => ''],
            ['current' => null, 'old' => null],
            ['current' => '0.00', 'old' => '0.00'],
            
            // Mixed types
            ['current' => 50.5, 'old' => '75.25'],
            ['current' => '25', 'old' => 30],
        ];
        
        foreach ($priceTestCases as $testCase) {
            $productData = generateProductDataWithPrices($testCase['current'], $testCase['old']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Verify both prices are always strings
            expect($result['current_price'])->toBeString();
            expect($result['old_price'])->toBeString();
            
            // Verify string conversion is correct
            if ($testCase['current'] !== null && $testCase['current'] !== '') {
                expect($result['current_price'])->toBe((string) $testCase['current']);
            } else {
                expect($result['current_price'])->toBe('');
            }
            
            if ($testCase['old'] !== null && $testCase['old'] !== '') {
                expect($result['old_price'])->toBe((string) $testCase['old']);
            } else {
                expect($result['old_price'])->toBe('');
            }
        }
        
        // Test with random generated prices
        for ($i = 0; $i < 50; $i++) {
            $currentPrice = $faker->randomElement([
                $faker->randomFloat(2, 0, 10000),
                (string) $faker->randomFloat(2, 0, 10000),
                $faker->numberBetween(0, 10000),
                (string) $faker->numberBetween(0, 10000)
            ]);
            
            $oldPrice = $faker->randomElement([
                $faker->randomFloat(2, 0, 12000),
                (string) $faker->randomFloat(2, 0, 12000),
                $faker->numberBetween(0, 12000),
                (string) $faker->numberBetween(0, 12000)
            ]);
            
            $productData = generateProductDataWithPrices($currentPrice, $oldPrice);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Always verify string type
            expect($result['current_price'])->toBeString();
            expect($result['old_price'])->toBeString();
            
            // Verify content matches expected string conversion
            expect($result['current_price'])->toBe((string) $currentPrice);
            expect($result['old_price'])->toBe((string) $oldPrice);
        }
        
    })->repeat(100);
    
    test('price formatting handles edge cases correctly', function () {
        $faker = Faker::create();
        
        $edgeCases = [
            // Zero values
            ['current' => 0, 'old' => 0, 'expected_current' => '0', 'expected_old' => '0'],
            ['current' => '0', 'old' => '0', 'expected_current' => '0', 'expected_old' => '0'],
            ['current' => 0.0, 'old' => 0.0, 'expected_current' => '0', 'expected_old' => '0'],
            
            // Empty/null values
            ['current' => '', 'old' => '', 'expected_current' => '', 'expected_old' => ''],
            ['current' => null, 'old' => null, 'expected_current' => '', 'expected_old' => ''],
            
            // Decimal precision
            ['current' => 99.99, 'old' => 129.99, 'expected_current' => '99.99', 'expected_old' => '129.99'],
            ['current' => '99.99', 'old' => '129.99', 'expected_current' => '99.99', 'expected_old' => '129.99'],
            
            // Large numbers
            ['current' => 999999.99, 'old' => 1199999.99, 'expected_current' => '999999.99', 'expected_old' => '1199999.99'],
        ];
        
        foreach ($edgeCases as $case) {
            $productData = generateProductDataWithPrices($case['current'], $case['old']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            expect($result['current_price'])->toBe($case['expected_current']);
            expect($result['old_price'])->toBe($case['expected_old']);
            expect($result['current_price'])->toBeString();
            expect($result['old_price'])->toBeString();
        }
        
    })->repeat(30);
    
    test('price formatting maintains backward compatibility with existing API consumers', function () {
        $faker = Faker::create();
        
        // Test that the format matches what existing API consumers expect
        $productData = generateProductDataWithPrices(99.99, 129.99);
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        // Verify the exact format expected by third-party integrations
        expect($result['current_price'])->toBe('99.99');
        expect($result['old_price'])->toBe('129.99');
        
        // Verify these are not numeric types that could break existing integrations
        expect($result['current_price'])->not->toBeInt();
        expect($result['current_price'])->not->toBeFloat();
        expect($result['old_price'])->not->toBeInt();
        expect($result['old_price'])->not->toBeFloat();
        
        // Test with integer prices
        $productData2 = generateProductDataWithPrices(100, 150);
        $resource2 = new ProductResource($productData2);
        $result2 = $resource2->toArray();
        
        expect($result2['current_price'])->toBe('100');
        expect($result2['old_price'])->toBe('150');
        expect($result2['current_price'])->toBeString();
        expect($result2['old_price'])->toBeString();
        
    });
    
});