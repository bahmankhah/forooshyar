<?php

use Tests\TestCase;

/**
 * Feature: woocommerce-product-refactor, Property 1: Configuration consistency
 * Validates: Requirements 2.10
 */

describe('Configuration Loading', function () {
    
    test('property 1: configuration consistency - default configuration should always load with valid structure and values', function () {
        // Property: For any configuration loading attempt, the system should return a valid configuration
        // with all required sections and sensible default values
        
        $config = $this->loadConfig('defaults');
        
        // Verify the configuration has the expected structure
        expect($config)->toHaveKey('config');
        expect($config)->toHaveKey('template_variables');
        
        $mainConfig = $config['config'];
        
        // Verify all required sections exist
        expect($mainConfig)->toHaveKey('general');
        expect($mainConfig)->toHaveKey('fields');
        expect($mainConfig)->toHaveKey('images');
        expect($mainConfig)->toHaveKey('cache');
        expect($mainConfig)->toHaveKey('api');
        
        // Verify general section structure
        expect($mainConfig['general'])->toHaveKey('show_variations');
        expect($mainConfig['general'])->toHaveKey('title_template');
        expect($mainConfig['general'])->toHaveKey('custom_suffix');
        expect($mainConfig['general'])->toHaveKey('language');
        
        // Verify general section values are valid
        expect($mainConfig['general']['show_variations'])->toBeBool();
        expect($mainConfig['general']['title_template'])->toBeString();
        expect($mainConfig['general']['custom_suffix'])->toBeString();
        expect($mainConfig['general']['language'])->toBeString();
        
        // Verify fields section has all required fields
        $requiredFields = [
            'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
            'old_price', 'availability', 'category_name', 'image_links',
            'image_link', 'page_url', 'short_desc', 'spec', 'date',
            'registry', 'guarantee'
        ];
        
        foreach ($requiredFields as $field) {
            expect($mainConfig['fields'])->toHaveKey($field);
            expect($mainConfig['fields'][$field])->toBeBool();
        }
        
        // Verify images section structure and values
        expect($mainConfig['images'])->toHaveKey('sizes');
        expect($mainConfig['images'])->toHaveKey('max_images');
        expect($mainConfig['images'])->toHaveKey('quality');
        
        expect($mainConfig['images']['sizes'])->toBeArray();
        expect($mainConfig['images']['max_images'])->toBeInt();
        expect($mainConfig['images']['quality'])->toBeInt();
        expect($mainConfig['images']['quality'])->toBeGreaterThan(0);
        expect($mainConfig['images']['quality'])->toBeLessThanOrEqual(100);
        
        // Verify cache section structure and values
        expect($mainConfig['cache'])->toHaveKey('enabled');
        expect($mainConfig['cache'])->toHaveKey('ttl');
        expect($mainConfig['cache'])->toHaveKey('auto_invalidate');
        
        expect($mainConfig['cache']['enabled'])->toBeBool();
        expect($mainConfig['cache']['ttl'])->toBeInt();
        expect($mainConfig['cache']['ttl'])->toBeGreaterThan(0);
        expect($mainConfig['cache']['auto_invalidate'])->toBeBool();
        
        // Verify API section structure and values
        expect($mainConfig['api'])->toHaveKey('max_per_page');
        expect($mainConfig['api'])->toHaveKey('rate_limit');
        expect($mainConfig['api'])->toHaveKey('timeout');
        
        expect($mainConfig['api']['max_per_page'])->toBeInt();
        expect($mainConfig['api']['max_per_page'])->toBeGreaterThan(0);
        expect($mainConfig['api']['rate_limit'])->toBeInt();
        expect($mainConfig['api']['rate_limit'])->toBeGreaterThan(0);
        expect($mainConfig['api']['timeout'])->toBeInt();
        expect($mainConfig['api']['timeout'])->toBeGreaterThan(0);
        
        // Verify template variables section
        expect($config['template_variables'])->toBeArray();
        expect($config['template_variables'])->not->toBeEmpty();
        
        // Verify template variables have Persian translations
        $expectedVariables = [
            'product_name', 'variation_name', 'category', 'sku', 'brand', 
            'custom_suffix', 'variation_suffix'
        ];
        
        foreach ($expectedVariables as $variable) {
            expect($config['template_variables'])->toHaveKey($variable);
            expect($config['template_variables'][$variable])->toBeString();
            expect($config['template_variables'][$variable])->not->toBeEmpty();
        }
        
    })->repeat(100); // Run 100 iterations as specified in design document
    
    test('localization configuration should load with valid structure', function () {
        $config = $this->loadConfig('localization');
        
        // Verify required keys exist
        expect($config)->toHaveKey('text_domain');
        expect($config)->toHaveKey('languages_path');
        expect($config)->toHaveKey('default_locale');
        expect($config)->toHaveKey('supported_locales');
        expect($config)->toHaveKey('rtl_locales');
        expect($config)->toHaveKey('date_formats');
        
        // Verify values are valid
        expect($config['text_domain'])->toBeString();
        expect($config['languages_path'])->toBeString();
        expect($config['default_locale'])->toBeString();
        expect($config['supported_locales'])->toBeArray();
        expect($config['rtl_locales'])->toBeArray();
        expect($config['date_formats'])->toBeArray();
        
        // Verify default locale is supported
        expect($config['supported_locales'])->toHaveKey($config['default_locale']);
        
        // Verify RTL locales are subset of supported locales
        foreach ($config['rtl_locales'] as $rtlLocale) {
            expect($config['supported_locales'])->toHaveKey($rtlLocale);
        }
        
    });
    
    test('plugin configuration should load with valid structure', function () {
        $config = $this->loadConfig('plugin');
        
        // Verify required sections exist
        expect($config)->toHaveKey('plugin');
        expect($config)->toHaveKey('admin');
        expect($config)->toHaveKey('api');
        
        // Verify plugin section
        expect($config['plugin'])->toHaveKey('name');
        expect($config['plugin'])->toHaveKey('version');
        expect($config['plugin'])->toHaveKey('text_domain');
        
        expect($config['plugin']['name'])->toBeString();
        expect($config['plugin']['version'])->toBeString();
        expect($config['plugin']['text_domain'])->toBeString();
        
        // Verify admin section
        expect($config['admin'])->toHaveKey('menu_position');
        expect($config['admin'])->toHaveKey('capability');
        expect($config['admin'])->toHaveKey('pages');
        
        expect($config['admin']['menu_position'])->toBeInt();
        expect($config['admin']['capability'])->toBeString();
        expect($config['admin']['pages'])->toBeArray();
        
        // Verify API section
        expect($config['api'])->toHaveKey('namespace');
        expect($config['api'])->toHaveKey('endpoints');
        
        expect($config['api']['namespace'])->toBeString();
        expect($config['api']['endpoints'])->toBeArray();
        
    });
    
});