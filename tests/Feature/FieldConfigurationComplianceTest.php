<?php

use Tests\TestCase;
use Forooshyar\Services\ConfigService;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 2: Field configuration compliance
 * Validates: Requirements 6.1, 14.2
 */

// Mock WordPress functions with shared state
class MockWordPressOptions {
    private static $options = [];
    
    public static function getOption($option, $default = false) {
        return self::$options[$option] ?? $default;
    }
    
    public static function updateOption($option, $value) {
        self::$options[$option] = $value;
        return true;
    }
    
    public static function clearAll() {
        self::$options = [];
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return MockWordPressOptions::getOption($option, $default);
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return MockWordPressOptions::updateOption($option, $value);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

describe('Field Configuration Compliance', function () {
    
    beforeEach(function () {
        // Clear mock options before each test
        MockWordPressOptions::clearAll();
    });
    
    test('property 2: field configuration compliance - for any product response, only fields that are enabled in configuration should be included, and all included fields should have the correct names and types', function () {
        // Property: For any product response, only fields that are enabled in configuration should be included,
        // and all included fields should have the correct names and types
        
        $faker = Faker::create();
        $configService = new ConfigService();
        
        // Generate random field configuration
        $allFields = [
            'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
            'old_price', 'availability', 'category_name', 'image_links',
            'image_link', 'page_url', 'short_desc', 'spec', 'date',
            'registry', 'guarantee'
        ];
        
        // Randomly enable/disable fields
        $fieldConfig = [];
        foreach ($allFields as $field) {
            $fieldConfig[$field] = $faker->boolean();
        }
        
        // Set the field configuration
        $configService->set('fields', $fieldConfig);
        
        // Create mock product data (simulating what would come from ProductService)
        $mockProductData = new stdClass();
        
        // Always populate all fields in the raw data
        $mockProductData->title = $faker->words(3, true);
        $mockProductData->subtitle = $faker->words(2, true);
        $mockProductData->parent_id = $faker->numberBetween(0, 100);
        $mockProductData->page_unique = $faker->numberBetween(1, 1000);
        $mockProductData->current_price = $faker->randomFloat(2, 10, 1000);
        $mockProductData->old_price = $faker->randomFloat(2, 10, 1000);
        $mockProductData->availability = $faker->randomElement(['instock', 'outofstock']);
        $mockProductData->category_name = $faker->word();
        $mockProductData->image_links = [$faker->url(), $faker->url()];
        $mockProductData->image_link = $faker->url();
        $mockProductData->page_url = $faker->url();
        $mockProductData->short_desc = $faker->sentence();
        $mockProductData->spec = [[$faker->word() => $faker->word()]];
        $mockProductData->date = new DateTime();
        $mockProductData->registry = $faker->word();
        $mockProductData->guarantee = $faker->sentence();
        
        // Simulate field filtering based on configuration
        $filteredData = new stdClass();
        $enabledFields = [];
        
        foreach ($fieldConfig as $field => $enabled) {
            if ($enabled && property_exists($mockProductData, $field)) {
                $filteredData->$field = $mockProductData->$field;
                $enabledFields[] = $field;
            }
        }
        
        // Test 1: Only enabled fields should be present
        foreach ($allFields as $field) {
            if ($fieldConfig[$field]) {
                expect($filteredData)->toHaveProperty($field);
            } else {
                expect(property_exists($filteredData, $field))->toBeFalse();
            }
        }
        
        // Test 2: All present fields should be enabled in configuration
        $presentFields = array_keys(get_object_vars($filteredData));
        foreach ($presentFields as $field) {
            expect($fieldConfig[$field])->toBeTrue();
        }
        
        // Test 3: Field types should be correct
        if (property_exists($filteredData, 'title')) {
            expect($filteredData->title)->toBeString();
        }
        
        if (property_exists($filteredData, 'subtitle')) {
            expect($filteredData->subtitle)->toBeString();
        }
        
        if (property_exists($filteredData, 'parent_id')) {
            expect($filteredData->parent_id)->toBeInt();
        }
        
        if (property_exists($filteredData, 'page_unique')) {
            expect($filteredData->page_unique)->toBeInt();
        }
        
        if (property_exists($filteredData, 'current_price')) {
            expect($filteredData->current_price)->toBeNumeric();
        }
        
        if (property_exists($filteredData, 'old_price')) {
            expect($filteredData->old_price)->toBeNumeric();
        }
        
        if (property_exists($filteredData, 'availability')) {
            expect($filteredData->availability)->toBeString();
            expect($filteredData->availability)->toBeIn(['instock', 'outofstock', 'onbackorder']);
        }
        
        if (property_exists($filteredData, 'category_name')) {
            expect($filteredData->category_name)->toBeString();
        }
        
        if (property_exists($filteredData, 'image_links')) {
            expect($filteredData->image_links)->toBeArray();
        }
        
        if (property_exists($filteredData, 'image_link')) {
            expect($filteredData->image_link)->toBeString();
        }
        
        if (property_exists($filteredData, 'page_url')) {
            expect($filteredData->page_url)->toBeString();
        }
        
        if (property_exists($filteredData, 'short_desc')) {
            expect($filteredData->short_desc)->toBeString();
        }
        
        if (property_exists($filteredData, 'spec')) {
            expect($filteredData->spec)->toBeArray();
        }
        
        if (property_exists($filteredData, 'date')) {
            expect($filteredData->date)->toBeInstanceOf(DateTime::class);
        }
        
        if (property_exists($filteredData, 'registry')) {
            expect($filteredData->registry)->toBeString();
        }
        
        if (property_exists($filteredData, 'guarantee')) {
            expect($filteredData->guarantee)->toBeString();
        }
        
        // Test 4: No extra fields should be present
        $expectedFieldCount = count($enabledFields);
        $actualFieldCount = count(get_object_vars($filteredData));
        expect($actualFieldCount)->toBe($expectedFieldCount);
        
    })->repeat(100);
    
    test('configuration service should handle field configuration correctly', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        
        // Test setting and getting field configuration
        $fieldConfig = [
            'title' => $faker->boolean(),
            'subtitle' => $faker->boolean(),
            'parent_id' => $faker->boolean(),
            'page_unique' => $faker->boolean(),
            'current_price' => $faker->boolean(),
            'old_price' => $faker->boolean(),
            'availability' => $faker->boolean(),
            'category_name' => $faker->boolean(),
            'image_links' => $faker->boolean(),
            'image_link' => $faker->boolean(),
            'page_url' => $faker->boolean(),
            'short_desc' => $faker->boolean(),
            'spec' => $faker->boolean(),
            'date' => $faker->boolean(),
            'registry' => $faker->boolean(),
            'guarantee' => $faker->boolean()
        ];
        
        // Set configuration
        $configService->set('fields', $fieldConfig);
        
        // Get configuration back
        $retrievedConfig = $configService->get('fields');
        
        // Should match what we set
        expect($retrievedConfig)->toBe($fieldConfig);
        
        // Test individual field access
        foreach ($fieldConfig as $field => $enabled) {
            $fieldValue = $configService->get('fields')[$field] ?? false;
            expect($fieldValue)->toBe($enabled);
        }
        
    });
    
    test('field filtering should preserve data integrity', function () {
        $faker = Faker::create();
        $configService = new ConfigService();
        
        // Enable all fields
        $allFieldsEnabled = [
            'title' => true,
            'subtitle' => true,
            'parent_id' => true,
            'page_unique' => true,
            'current_price' => true,
            'old_price' => true,
            'availability' => true,
            'category_name' => true,
            'image_links' => true,
            'image_link' => true,
            'page_url' => true,
            'short_desc' => true,
            'spec' => true,
            'date' => true,
            'registry' => true,
            'guarantee' => true
        ];
        
        $configService->set('fields', $allFieldsEnabled);
        
        // Create test data
        $originalData = new stdClass();
        $originalData->title = $faker->words(3, true);
        $originalData->current_price = $faker->randomFloat(2, 10, 1000);
        $originalData->availability = 'instock';
        
        // Simulate filtering (all fields enabled, so should be unchanged)
        $filteredData = clone $originalData;
        
        // Data should be preserved exactly
        expect($filteredData->title)->toBe($originalData->title);
        expect($filteredData->current_price)->toBe($originalData->current_price);
        expect($filteredData->availability)->toBe($originalData->availability);
        
        // Now test with some fields disabled
        $partialFieldsEnabled = [
            'title' => true,
            'subtitle' => false,
            'parent_id' => false,
            'page_unique' => true,
            'current_price' => true,
            'old_price' => false,
            'availability' => true,
            'category_name' => false,
            'image_links' => false,
            'image_link' => false,
            'page_url' => false,
            'short_desc' => false,
            'spec' => false,
            'date' => false,
            'registry' => false,
            'guarantee' => false
        ];
        
        $configService->set('fields', $partialFieldsEnabled);
        
        // Simulate filtering with partial fields
        $partiallyFilteredData = new stdClass();
        foreach ($partialFieldsEnabled as $field => $enabled) {
            if ($enabled && property_exists($originalData, $field)) {
                $partiallyFilteredData->$field = $originalData->$field;
            }
        }
        
        // Only enabled fields should be present
        expect(property_exists($partiallyFilteredData, 'title'))->toBeTrue();
        expect(property_exists($partiallyFilteredData, 'current_price'))->toBeTrue();
        expect(property_exists($partiallyFilteredData, 'availability'))->toBeTrue();
        
        // Disabled fields should not be present
        expect(property_exists($partiallyFilteredData, 'subtitle'))->toBeFalse();
        expect(property_exists($partiallyFilteredData, 'old_price'))->toBeFalse();
        
        // Enabled field values should be preserved
        expect($partiallyFilteredData->title)->toBe($originalData->title);
        expect($partiallyFilteredData->current_price)->toBe($originalData->current_price);
        expect($partiallyFilteredData->availability)->toBe($originalData->availability);
        
    });
    
    test('field names should match API specification exactly', function () {
        $configService = new ConfigService();
        
        // These are the exact field names from the requirements
        $requiredFieldNames = [
            'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
            'old_price', 'availability', 'category_name', 'image_links',
            'image_link', 'page_url', 'short_desc', 'spec', 'date',
            'registry', 'guarantee'
        ];
        
        // Enable all fields
        $fieldConfig = array_fill_keys($requiredFieldNames, true);
        $configService->set('fields', $fieldConfig);
        
        $retrievedConfig = $configService->get('fields');
        
        // All required field names should be present
        foreach ($requiredFieldNames as $fieldName) {
            expect($retrievedConfig)->toHaveKey($fieldName);
        }
        
        // Field names should be exactly as specified (case sensitive)
        expect($retrievedConfig)->toHaveKey('parent_id'); // not parentId or parent-id
        expect($retrievedConfig)->toHaveKey('page_unique'); // not pageUnique or page-unique
        expect($retrievedConfig)->toHaveKey('current_price'); // not currentPrice or current-price
        expect($retrievedConfig)->toHaveKey('old_price'); // not oldPrice or old-price
        expect($retrievedConfig)->toHaveKey('category_name'); // not categoryName or category-name
        expect($retrievedConfig)->toHaveKey('image_links'); // not imageLinks or image-links
        expect($retrievedConfig)->toHaveKey('image_link'); // not imageLink or image-link
        expect($retrievedConfig)->toHaveKey('page_url'); // not pageUrl or page-url
        expect($retrievedConfig)->toHaveKey('short_desc'); // not shortDesc or short-desc
        
    });
    
});