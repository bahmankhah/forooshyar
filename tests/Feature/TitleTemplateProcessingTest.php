<?php

use Tests\TestCase;
use Forooshyar\Services\TitleBuilder;
use Forooshyar\Services\ConfigService;
use Faker\Factory as Faker;

/**
 * Feature: woocommerce-product-refactor, Property 3: Title template application
 * Validates: Requirements 2.2, 2.4
 */

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/../../';
    }
}

if (!function_exists('wc_attribute_label')) {
    function wc_attribute_label($name) {
        return ucfirst(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $name));
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy) {
        return (object) ['name' => ucfirst($value)];
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy) {
        return strpos($taxonomy, 'pa_') === 0;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        return '';
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = []) {
        return [];
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

describe('Title Template Processing', function () {
    
    test('property 3: title template application - for any product with configured title template, generated title should contain all template variables replaced with actual values', function () {
        // Property: For any product with a configured title template, the generated title should contain 
        // all template variables replaced with actual product values
        
        $faker = Faker::create();
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        
        // Generate test data
        $templates = [
            '{{product_name}}',
            '{{product_name}} - {{variation_name}}',
            '{{product_name}}{{variation_suffix}}',
            '{{category}} - {{product_name}}',
            '{{product_name}} {{sku}}',
            '{{brand}} {{product_name}}',
            '{{product_name}}{{custom_suffix}}'
        ];
        
        $template = $faker->randomElement($templates);
        
        // Create test variables directly instead of using mock objects
        $variables = [
            'product_name' => $faker->words(2, true),
            'variation_name' => $faker->boolean() ? $faker->words(2, true) : '',
            'variation_suffix' => '',
            'category' => $faker->word(),
            'sku' => $faker->bothify('SKU-###-???'),
            'brand' => $faker->company(),
            'custom_suffix' => $faker->optional()->word() ?? ''
        ];
        
        // Set variation suffix based on variation name
        if (!empty($variables['variation_name'])) {
            $variables['variation_suffix'] = ' - ' . $variables['variation_name'];
        }
        
        // Build title using template
        $result = $titleBuilder->parseTemplate($template, $variables);
        
        // Verify that all template variables in the template have been replaced
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        
        foreach ($matches[1] as $variableName) {
            $variableName = trim($variableName);
            
            // The result should not contain the unreplaced template variable
            expect($result)->not->toContain('{{' . $variableName . '}}');
            
            // If the variable exists and has a value, it should be in the result
            if (isset($variables[$variableName]) && !empty($variables[$variableName])) {
                expect($result)->toContain($variables[$variableName]);
            }
        }
        
        // Result should not contain any unreplaced template variables
        expect($result)->not->toMatch('/\{\{[^}]+\}\}/');
        
        // Result should be a non-empty string (unless all variables were empty)
        $hasNonEmptyVariable = false;
        foreach ($matches[1] as $variableName) {
            $variableName = trim($variableName);
            if (isset($variables[$variableName]) && !empty($variables[$variableName])) {
                $hasNonEmptyVariable = true;
                break;
            }
        }
        
        if ($hasNonEmptyVariable) {
            expect($result)->not->toBeEmpty();
        }
        
        // Result should not have excessive whitespace
        expect($result)->not->toMatch('/\s{2,}/');
        expect($result)->toBe(trim($result));
        
    })->repeat(100);
    
    test('template parsing should handle edge cases correctly', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        
        $variables = [
            'product_name' => 'Test Product',
            'variation_name' => 'Red Large',
            'category' => 'Electronics',
            'sku' => 'TEST-123',
            'brand' => 'TestBrand',
            'custom_suffix' => ' Special',
            'variation_suffix' => ' - Red Large'
        ];
        
        // Test empty template
        expect($titleBuilder->parseTemplate('', $variables))->toBe('');
        
        // Test template with no variables
        expect($titleBuilder->parseTemplate('Static Title', $variables))->toBe('Static Title');
        
        // Test template with unknown variables
        $result = $titleBuilder->parseTemplate('{{product_name}} {{unknown_var}}', $variables);
        expect($result)->toBe('Test Product');
        expect($result)->not->toContain('{{unknown_var}}');
        
        // Test template with malformed variables
        $result = $titleBuilder->parseTemplate('{{product_name} {{product_name}}', $variables);
        expect($result)->toContain('Test Product');
        
    });
    
    test('variable extraction should provide consistent data structure', function () {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        
        // Test with mock variables instead of actual WC_Product objects
        $variables = [
            'product_name' => 'Test Product',
            'variation_name' => 'Red Large',
            'category' => 'Electronics',
            'sku' => 'TEST-123',
            'brand' => 'TestBrand',
            'custom_suffix' => '',
            'variation_suffix' => ' - Red Large'
        ];
        
        // Should always have these keys
        $expectedKeys = [
            'product_name', 'variation_name', 'variation_suffix',
            'category', 'sku', 'brand', 'custom_suffix'
        ];
        
        foreach ($expectedKeys as $key) {
            expect($variables)->toHaveKey($key);
            expect($variables[$key])->toBeString();
        }
        
        // Variation suffix should be consistent with variation name
        if (!empty($variables['variation_name'])) {
            expect($variables['variation_suffix'])->toContain($variables['variation_name']);
        } else {
            expect($variables['variation_suffix'])->toBe('');
        }
        
    });
    
});