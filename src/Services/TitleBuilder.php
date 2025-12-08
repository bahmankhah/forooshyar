<?php

namespace Forooshyar\Services;

use WC_Product;

class TitleBuilder
{
    /** @var ConfigService */
    private $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Build product title using template
     */
    public function build(WC_Product $product, ?WC_Product $variation = null): string
    {
        $generalConfig = $this->configService->get('general', []);
        $template = $generalConfig['title_template'] ?? '{{product_name}}{{variation_suffix}}';
        
        $variables = $this->getVariables($product, $variation);
        
        return $this->parseTemplate($template, $variables);
    }

    /**
     * Parse template with variables
     */
    public function parseTemplate(string $template, array $variables): string
    {
        $result = $template;
        
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $result = str_replace($placeholder, $value, $result);
        }
        
        // Clean up any remaining placeholders
        $result = preg_replace('/\{\{[^}]+\}\}/', '', $result);
        
        // Clean up extra spaces and trim
        $result = preg_replace('/\s+/', ' ', $result);
        $result = trim($result);
        
        return $result;
    }

    /**
     * Get all available variables for a product
     */
    public function getVariables(WC_Product $product, ?WC_Product $variation = null): array
    {
        $variables = [];
        
        // Product name
        $variables['product_name'] = $this->cleanAndDecode($product->get_name());
        
        // Variation name and suffix
        if ($variation) {
            $variables['variation_name'] = $this->buildVariationName($product, $variation);
            $variables['variation_suffix'] = $variables['variation_name'] ? ' - ' . $variables['variation_name'] : '';
        } else {
            $variables['variation_name'] = '';
            $variables['variation_suffix'] = '';
        }
        
        // Category
        $categoryIds = $product->get_category_ids();
        if (!empty($categoryIds)) {
            $category = get_term_by('id', end($categoryIds), 'product_cat');
            $variables['category'] = $category ? $this->cleanAndDecode($category->name) : '';
        } else {
            $variables['category'] = '';
        }
        
        // SKU
        $variables['sku'] = $product->get_sku() ?: '';
        
        // Brand (from product attributes or meta)
        $variables['brand'] = $this->extractBrand($product);
        
        // Custom suffix from configuration
        $generalConfig = $this->configService->get('general', []);
        $variables['custom_suffix'] = $generalConfig['custom_suffix'] ?? '';
        
        return $variables;
    }

    /**
     * Build variation name from attributes
     */
    private function buildVariationName(WC_Product $parentProduct, WC_Product $variation): string
    {
        $attributes = $variation->get_attributes();
        $specParts = [];

        foreach ($attributes as $attrKey => $attrValue) {
            // Skip empty or problematic values
            if (empty($attrValue) || in_array(strtolower($attrValue), ['variable', 'any', 'exclude-from-search', 'external'])) {
                continue;
            }

            $taxonomyKey = $attrKey;
            
            // Get the attribute label
            $label = wc_attribute_label($taxonomyKey);
            
            if (empty($label)) {
                // Create label from key
                $cleanKey = $attrKey;
                
                if (strpos($cleanKey, 'pa_') === 0) {
                    $cleanKey = substr($cleanKey, 3);
                }
                
                $cleanKey = $this->cleanAndDecode($cleanKey);
                $label = ucwords(str_replace(['-', '_'], ' ', $cleanKey));
            } else {
                $label = $this->cleanAndDecode($label);
            }

            // Get the attribute value/term name
            $finalValue = '';
            
            if (strpos($attrKey, 'pa_') === 0 && taxonomy_exists($taxonomyKey)) {
                $term = get_term_by('slug', $attrValue, $taxonomyKey);
                
                if ($term && !is_wp_error($term)) {
                    $finalValue = $term->name;
                } else {
                    $finalValue = $attrValue;
                }
            } else {
                $finalValue = $attrValue;
            }
            
            $finalValue = $this->cleanAndDecode($finalValue);
            
            // Clean up labels and values
            $label = trim(str_replace(['-', '_'], ' ', $label));
            $finalValue = trim(preg_replace('/^pa_/i', '', $finalValue));
            
            // Only add if both are meaningful
            if (!empty($label) && !empty($finalValue) && 
                !in_array(strtolower($finalValue), ['variable', 'any', 'exclude-from-search', 'external'])) {
                $specParts[] = "{$label}: {$finalValue}";
            }
        }

        return implode(' - ', $specParts);
    }

    /**
     * Extract brand from product
     */
    private function extractBrand(WC_Product $product): string
    {
        // Try to get brand from various sources
        $brandSources = [
            'brand',
            'pa_brand',
            '_brand',
            'product_brand'
        ];
        
        foreach ($brandSources as $source) {
            // Try as attribute
            $attributes = $product->get_attributes();
            if (isset($attributes[$source])) {
                $attribute = $attributes[$source];
                if (is_array($attribute['options'])) {
                    $brand = implode(', ', $attribute['options']);
                } else {
                    $brand = $attribute['options'];
                }
                
                if (!empty($brand)) {
                    return $this->cleanAndDecode($brand);
                }
            }
            
            // Try as meta field
            $meta = get_post_meta($product->get_id(), $source, true);
            if (!empty($meta)) {
                return $this->cleanAndDecode($meta);
            }
            
            // Try as taxonomy
            if (taxonomy_exists($source)) {
                $terms = wp_get_post_terms($product->get_id(), $source, ['fields' => 'names']);
                if (!empty($terms) && !is_wp_error($terms)) {
                    return $this->cleanAndDecode(implode(', ', $terms));
                }
            }
        }
        
        return '';
    }

    /**
     * Clean and decode string values
     */
    private function cleanAndDecode(string $value): string
    {
        // URL decode multiple times to handle nested encoding
        $decoded = $value;
        for ($i = 0; $i < 5; $i++) {
            $temp = urldecode($decoded);
            if ($temp === $decoded) {
                break;
            }
            $decoded = $temp;
        }
        
        // Remove pa_ prefixes
        $decoded = preg_replace('/\bpa_(\w+)/i', '$1', $decoded);
        
        // Clean up whitespace
        $decoded = trim($decoded);
        
        return $decoded;
    }
}