<?php

use Tests\TestCase;
use Forooshyar\Resources\ProductResource;
use Faker\Factory as Faker;
use DateTime;
use DateTimeZone;

/**
 * Feature: woocommerce-product-refactor, Property 11: Date structure consistency
 * Validates: Requirements 6.8, 14.5
 */

describe('Date Structure', function () {
    
    function generateProductDataWithDate($date) {
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
            'spec' => [],
            'date' => $date,
            'registry' => '',
            'guarantee' => ''
        ];
    }
    
    // Mock WooCommerce date object for testing
    class MockWCDate {
        private $dateTime;
        private $timezone;
        
        public function __construct($dateTime, $timezone = null) {
            $this->dateTime = $dateTime instanceof DateTime ? $dateTime : new DateTime($dateTime);
            $this->timezone = $timezone ?: new DateTimeZone('UTC');
        }
        
        public function date($format) {
            return $this->dateTime->format($format);
        }
        
        public function getTimezone() {
            return $this->timezone;
        }
    }
    
    test('property 11: date structure consistency - for any product response that includes date information, the date field should be an object with date, timezone_type, and timezone properties', function () {
        // Property: For any product response that includes date information, the date field should be 
        // an object with date, timezone_type, and timezone properties
        
        $faker = Faker::create();
        
        // Test various date input formats
        $dateTestCases = [
            // DateTime object
            [
                'input' => new DateTime('2023-12-01 15:30:45'),
                'description' => 'DateTime object'
            ],
            
            // DateTime with specific timezone
            [
                'input' => new DateTime('2023-12-01 15:30:45', new DateTimeZone('Europe/London')),
                'description' => 'DateTime with timezone'
            ],
            
            // String date
            [
                'input' => '2023-12-01 15:30:45',
                'description' => 'string date'
            ],
            
            // ISO format string
            [
                'input' => '2023-12-01T15:30:45Z',
                'description' => 'ISO format string'
            ],
            
            // Mock WooCommerce date object
            [
                'input' => new MockWCDate('2023-12-01 15:30:45', new DateTimeZone('America/New_York')),
                'description' => 'WooCommerce date object'
            ],
            
            // Already formatted array (should remain as-is)
            [
                'input' => [
                    'date' => '2023-12-01 15:30:45.000000',
                    'timezone_type' => 3,
                    'timezone' => 'UTC'
                ],
                'description' => 'already formatted array'
            ],
            
            // Empty cases
            [
                'input' => null,
                'description' => 'null value'
            ],
            
            [
                'input' => '',
                'description' => 'empty string'
            ]
        ];
        
        foreach ($dateTestCases as $testCase) {
            $productData = generateProductDataWithDate($testCase['input']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Verify date is always an array
            expect($result['date'])->toBeArray();
            
            if (empty($testCase['input'])) {
                // Empty inputs should result in empty array
                expect($result['date'])->toBeEmpty();
            } else {
                if (is_array($testCase['input']) && isset($testCase['input']['date'])) {
                    // Already formatted - should remain as-is
                    expect($result['date'])->toBe($testCase['input']);
                } else {
                    // Should be formatted with required structure
                    expect($result['date'])->toHaveKey('date');
                    expect($result['date'])->toHaveKey('timezone_type');
                    expect($result['date'])->toHaveKey('timezone');
                    
                    // Verify types
                    expect($result['date']['date'])->toBeString();
                    expect($result['date']['timezone_type'])->toBeInt();
                    expect($result['date']['timezone'])->toBeString();
                    
                    // Verify timezone_type is 3 (as per PHP DateTime standard)
                    expect($result['date']['timezone_type'])->toBe(3);
                    
                    // Verify timezone is a valid string
                    expect($result['date']['timezone'])->not->toBeEmpty();
                }
            }
        }
        
        // Test with random generated dates
        for ($i = 0; $i < 50; $i++) {
            $randomDate = $faker->dateTimeBetween('-2 years', '+1 year');
            $timezones = ['UTC', 'Europe/London', 'America/New_York', 'Asia/Tokyo', 'Australia/Sydney'];
            $randomTimezone = $faker->randomElement($timezones);
            
            $randomDate->setTimezone(new DateTimeZone($randomTimezone));
            
            $productData = generateProductDataWithDate($randomDate);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            // Always verify structure
            expect($result['date'])->toBeArray();
            expect($result['date'])->toHaveKey('date');
            expect($result['date'])->toHaveKey('timezone_type');
            expect($result['date'])->toHaveKey('timezone');
            
            // Verify content
            expect($result['date']['timezone_type'])->toBe(3);
            expect($result['date']['timezone'])->toBe($randomTimezone);
            
            // Verify date format includes microseconds
            expect($result['date']['date'])->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}/');
        }
        
    })->repeat(100);
    
    test('date formatting handles edge cases correctly', function () {
        $faker = Faker::create();
        
        $edgeCases = [
            // Null and empty
            [
                'input' => null,
                'expected' => []
            ],
            [
                'input' => '',
                'expected' => []
            ],
            
            // Invalid date string (should result in empty array)
            [
                'input' => 'invalid-date-string',
                'expected' => []
            ],
            
            // Valid date formats
            [
                'input' => '2023-01-01',
                'expected_structure' => true
            ],
            [
                'input' => '2023-12-31 23:59:59',
                'expected_structure' => true
            ]
        ];
        
        foreach ($edgeCases as $case) {
            $productData = generateProductDataWithDate($case['input']);
            $resource = new ProductResource($productData);
            $result = $resource->toArray();
            
            if (isset($case['expected'])) {
                expect($result['date'])->toBe($case['expected']);
            } elseif (isset($case['expected_structure'])) {
                expect($result['date'])->toBeArray();
                expect($result['date'])->toHaveKey('date');
                expect($result['date'])->toHaveKey('timezone_type');
                expect($result['date'])->toHaveKey('timezone');
            }
        }
        
    })->repeat(30);
    
    test('date formatting maintains backward compatibility', function () {
        $faker = Faker::create();
        
        // Test the exact format expected by existing API consumers
        $testDate = new DateTime('2023-12-01 15:30:45.123456', new DateTimeZone('UTC'));
        
        $productData = generateProductDataWithDate($testDate);
        $resource = new ProductResource($productData);
        $result = $resource->toArray();
        
        // Verify the exact structure that third-party integrations expect
        expect($result['date'])->toBeArray();
        expect($result['date'])->toHaveKeys(['date', 'timezone_type', 'timezone']);
        
        // Verify exact format
        expect($result['date']['timezone_type'])->toBe(3);
        expect($result['date']['timezone'])->toBe('UTC');
        expect($result['date']['date'])->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}/');
        
        // Verify these are not other types that could break existing integrations
        expect($result['date'])->not->toBeString();
        expect($result['date'])->not->toBeNull();
        expect($result['date']['date'])->not->toBeArray();
        expect($result['date']['timezone_type'])->not->toBeString();
        expect($result['date']['timezone'])->not->toBeArray();
        
        // Test with already formatted date (should not double-format)
        $alreadyFormatted = [
            'date' => '2023-12-01 15:30:45.123456',
            'timezone_type' => 3,
            'timezone' => 'Europe/London'
        ];
        
        $productData2 = generateProductDataWithDate($alreadyFormatted);
        $resource2 = new ProductResource($productData2);
        $result2 = $resource2->toArray();
        
        expect($result2['date'])->toBe($alreadyFormatted);
        
        // Test with different timezones
        $timezones = ['UTC', 'Europe/London', 'America/New_York', 'Asia/Tokyo'];
        
        foreach ($timezones as $timezone) {
            $dateWithTz = new DateTime('2023-12-01 15:30:45', new DateTimeZone($timezone));
            $productData3 = generateProductDataWithDate($dateWithTz);
            $resource3 = new ProductResource($productData3);
            $result3 = $resource3->toArray();
            
            expect($result3['date']['timezone'])->toBe($timezone);
            expect($result3['date']['timezone_type'])->toBe(3);
        }
        
    });
    
});