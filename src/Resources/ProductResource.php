<?php

namespace Forooshyar\Resources;

use Forooshyar\WPLite\JsonResource;
use DateTime;
use WC_Product;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $product = $this->data;
        
        // Handle both stdClass objects and arrays
        if (is_object($product)) {
            $product = (array) $product;
        }
        
        // Always include all fields for backward compatibility
        // Empty fields should be empty strings, not omitted (Requirement 14.8)
        $result = [
            'title' => isset($product['title']) ? (string) $product['title'] : '',
            'subtitle' => isset($product['subtitle']) ? (string) $product['subtitle'] : '',
            'parent_id' => isset($product['parent_id']) ? (int) $product['parent_id'] : 0,
            'page_unique' => isset($product['page_unique']) ? (int) $product['page_unique'] : 0,
            'current_price' => isset($product['current_price']) ? $this->formatPrice($product['current_price']) : '',
            'old_price' => isset($product['old_price']) ? $this->formatPrice($product['old_price']) : '',
            'availability' => isset($product['availability']) ? (string) $product['availability'] : '',
            'category_name' => isset($product['category_name']) ? (string) $product['category_name'] : '',
            'image_links' => isset($product['image_links']) ? $this->formatImageLinks($product['image_links']) : [],
            'image_link' => isset($product['image_link']) ? (string) $product['image_link'] : '',
            'page_url' => isset($product['page_url']) ? (string) $product['page_url'] : '',
            'short_desc' => isset($product['short_desc']) ? (string) $product['short_desc'] : '',
            'spec' => isset($product['spec']) ? $this->formatSpecifications($product['spec']) : [],
            'date' => isset($product['date']) ? $this->formatDate($product['date']) : [],
            'registry' => isset($product['registry']) ? (string) $product['registry'] : '',
            'guarantee' => isset($product['guarantee']) ? (string) $product['guarantee'] : ''
        ];
        
        return $result;
    }
    
    /**
     * Format price as string to maintain backward compatibility
     *
     * @param mixed $price
     * @return string
     */
    private function formatPrice($price): string
    {
        if (is_null($price) || $price === '') {
            return '';
        }
        
        return (string) $price;
    }
    
    /**
     * Format image links as array of strings
     *
     * @param mixed $imageLinks
     * @return array
     */
    private function formatImageLinks($imageLinks): array
    {
        if (!is_array($imageLinks)) {
            return [];
        }
        
        return array_map('strval', $imageLinks);
    }
    
    /**
     * Format specifications as array containing single object
     * Maintains exact backward compatibility
     *
     * @param mixed $spec
     * @return array
     */
    private function formatSpecifications($spec): array
    {
        if (empty($spec)) {
            return [];
        }
        
        // If already formatted as array with single object, return as-is
        if (is_array($spec) && count($spec) === 1 && isset($spec[0]) && is_array($spec[0])) {
            return $spec;
        }
        
        // If it's an object or associative array, wrap in array
        if (is_object($spec) || (is_array($spec) && !empty($spec))) {
            return [(array) $spec];
        }
        
        return [];
    }
    
    /**
     * Format date object maintaining exact structure
     *
     * @param mixed $date
     * @return array
     */
    private function formatDate($date): array
    {
        if (empty($date)) {
            return [];
        }
        
        // If already formatted as array, return as-is
        if (is_array($date) && isset($date['date'])) {
            return $date;
        }
        
        // Handle DateTime objects
        if ($date instanceof DateTime) {
            return [
                'date' => $date->format('Y-m-d H:i:s.u'),
                'timezone_type' => 3,
                'timezone' => $date->getTimezone()->getName()
            ];
        }
        
        // Handle WooCommerce date objects
        if (is_object($date) && method_exists($date, 'date')) {
            $dateTime = $date->date('Y-m-d H:i:s.u');
            $timezone = $date->getTimezone();
            
            return [
                'date' => $dateTime,
                'timezone_type' => 3,
                'timezone' => $timezone ? $timezone->getName() : 'UTC'
            ];
        }
        
        // Handle string dates
        if (is_string($date)) {
            try {
                $dateTime = new DateTime($date);
                return [
                    'date' => $dateTime->format('Y-m-d H:i:s.u'),
                    'timezone_type' => 3,
                    'timezone' => $dateTime->getTimezone()->getName()
                ];
            } catch (\Exception $e) {
                return [];
            }
        }
        
        return [];
    }
    
    /**
     * Override collection method to return array of transformed products
     *
     * @param array $items
     * @return array
     */
    public static function collection($items): array
    {
        if (!is_array($items)) {
            return [];
        }
        
        return array_map(function ($item) {
            return (new static($item))->toArray();
        }, $items);
    }
}