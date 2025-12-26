<?php

namespace Forooshyar\Resources;

use Forooshyar\WPLite\JsonResource;

class ProductCollectionResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     * Maintains exact backward compatibility with existing JSON structure.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->data;
        
        // Handle both stdClass objects and arrays
        if (is_object($data)) {
            $data = (array) $data;
        }
        
        // Extract products array and transform using ProductResource
        $products = isset($data['products']) ? $data['products'] : [];
        
        // Transform products if they need transformation
        $transformedProducts = [];
        if (is_array($products)) {
            foreach ($products as $product) {
                if (is_object($product) && !is_array($product)) {
                    // If it's an object, convert to array for consistency
                    $transformedProducts[] = (array) $product;
                } else {
                    $transformedProducts[] = $product;
                }
            }
        }
        
        return [
            'count' => isset($data['count']) ? (int) $data['count'] : count($transformedProducts),
            'max_pages' => isset($data['max_pages']) ? (int) $data['max_pages'] : 1,
            'products' => $transformedProducts
        ];
    }
    
    /**
     * Create a new collection resource instance
     *
     * @param array $products
     * @param int|null $count
     * @param int|null $maxPages
     * @return static
     */
    public static function make($products, ?int $count = null, ?int $maxPages = null): self
    {
        $data = [
            'products' => $products,
            'count' => $count ?? count($products),
            'max_pages' => $maxPages ?? 1
        ];
        
        return new static($data);
    }
    
    /**
     * Create collection from raw data array
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        return new static($data);
    }
}