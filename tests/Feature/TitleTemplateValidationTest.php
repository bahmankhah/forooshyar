<?php

use Tests\TestCase;
use Forooshyar\Services\ConfigService;

/**
 * Feature: woocommerce-product-refactor, Property 4: Template validation consistency
 * Validates: Requirements 2.3, 2.10
 */

describe('Title Template Validation', function () {
    
    beforeEach(function () {
        $this->configService = new ConfigService();
    });
    
    test('property 4: template validation consistency - valid templates should always pass validation', function () {
        // Property: For any valid title template syntax, the validation should return success
        
        $validTemplates = [
            '{{product_name}}',
            '{{product_name}} - {{variation_name}}',
            '{{product_name}}{{variation_suffix}}',
            '{{category}} - {{product_name}}',
            '{{brand}} {{product_name}} {{sku}}',
            '{{product_name}} {{custom_suffix}}',
            'Static text {{product_name}} more text',
            '{{product_name}} ({{category}})',
            '{{brand}}: {{product_name}} - {{variation_name}}',
            '{{product_name}} | {{sku}} | {{category}}'
        ];
        
        foreach ($validTemplates as $template) {
            $result = $this->configService->validateTitleTemplate($template);
            
            expect($result)->toHaveKey('valid');
            expect($result)->toHaveKey('errors');
            expect($result['valid'])->toBeTrue("Template '{$template}' should be valid");
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->toBeEmpty("Template '{$template}' should have no errors");
        }
        
    })->repeat(100);
    
    test('property 4: template validation consistency - invalid templates should always fail validation with Persian error messages', function () {
        // Property: For any invalid title template syntax, the validation should return failure with Persian errors
        
        $invalidTemplates = [
            '{{invalid_variable}}' => 'متغیر نامعتبر',
            '{{product_name' => 'آکولادهای باز و بسته برابر نیستند',
            'product_name}}' => 'آکولادهای باز و بسته برابر نیستند',
            '{{product_name}}{{' => 'آکولادهای باز و بسته برابر نیستند',
            '{{{{product_name}}}}' => 'آکولادهای تو در تو مجاز نیستند',
            '{{product_name {{variation_name}}}}' => 'آکولادهای تو در تو مجاز نیستند',
            '{{unknown_var}} {{another_invalid}}' => 'متغیر نامعتبر',
            '{{}}' => 'متغیر نامعتبر',
            '{{ }}' => 'متغیر نامعتبر'
        ];
        
        foreach ($invalidTemplates as $template => $expectedErrorType) {
            $result = $this->configService->validateTitleTemplate($template);
            
            expect($result)->toHaveKey('valid');
            expect($result)->toHaveKey('errors');
            expect($result['valid'])->toBeFalse("Template '{$template}' should be invalid");
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->not->toBeEmpty("Template '{$template}' should have errors");
            
            // Check that error messages are in Persian
            foreach ($result['errors'] as $error) {
                expect($error)->toBeString();
                expect($error)->not->toBeEmpty();
                // Verify Persian characters are present (basic check)
                expect(preg_match('/[\x{0600}-\x{06FF}]/u', $error))->toBe(1, "Error message should be in Persian: {$error}");
            }
        }
        
    })->repeat(100);
    
    test('property 4: template validation consistency - templates with mixed valid and invalid variables should fail appropriately', function () {
        // Property: For any template with both valid and invalid variables, validation should identify only the invalid ones
        
        $mixedTemplates = [
            '{{product_name}} - {{invalid_var}}',
            '{{valid_category}} {{unknown}} {{product_name}}',
            '{{sku}} and {{fake_variable}} with {{brand}}'
        ];
        
        foreach ($mixedTemplates as $template) {
            $result = $this->configService->validateTitleTemplate($template);
            
            expect($result)->toHaveKey('valid');
            expect($result)->toHaveKey('errors');
            expect($result['valid'])->toBeFalse("Mixed template '{$template}' should be invalid");
            expect($result['errors'])->toBeArray();
            expect($result['errors'])->not->toBeEmpty("Mixed template '{$template}' should have errors");
            
            // Verify error messages mention invalid variables specifically
            $errorText = implode(' ', $result['errors']);
            expect($errorText)->toContain('متغیر نامعتبر');
        }
        
    })->repeat(50);
    
    test('available template variables should be consistent and in Persian', function () {
        // Verify that available variables are consistent and have Persian descriptions
        
        $variables = $this->configService->getAvailableVariables();
        
        expect($variables)->toBeArray();
        expect($variables)->not->toBeEmpty();
        
        $expectedVariables = [
            'product_name', 'variation_name', 'category', 'sku', 
            'brand', 'custom_suffix', 'variation_suffix'
        ];
        
        foreach ($expectedVariables as $variable) {
            expect($variables)->toHaveKey($variable);
            expect($variables[$variable])->toBeString();
            expect($variables[$variable])->not->toBeEmpty();
            // Verify Persian characters are present
            expect(preg_match('/[\x{0600}-\x{06FF}]/u', $variables[$variable]))->toBe(1, 
                "Variable description should be in Persian: {$variables[$variable]}");
        }
        
    });
    
    test('edge cases in template validation should be handled correctly', function () {
        // Test edge cases that might cause issues
        
        $edgeCases = [
            '' => true, // Empty template should be valid
            'No variables here' => true, // Plain text should be valid
            '   {{product_name}}   ' => true, // Whitespace should be handled
            '{{product_name}}{{product_name}}' => true, // Duplicate variables should be valid
            'Multiple {{product_name}} instances {{product_name}} here' => true
        ];
        
        foreach ($edgeCases as $template => $shouldBeValid) {
            $result = $this->configService->validateTitleTemplate($template);
            
            expect($result)->toHaveKey('valid');
            expect($result)->toHaveKey('errors');
            
            if ($shouldBeValid) {
                expect($result['valid'])->toBeTrue("Edge case template '{$template}' should be valid");
                expect($result['errors'])->toBeEmpty("Edge case template '{$template}' should have no errors");
            } else {
                expect($result['valid'])->toBeFalse("Edge case template '{$template}' should be invalid");
                expect($result['errors'])->not->toBeEmpty("Edge case template '{$template}' should have errors");
            }
        }
        
    });
    
});