<?php

use Tests\TestCase;
use Forooshyar\Services\ProductService;
use Forooshyar\Services\ConfigService;
use Forooshyar\Services\TitleBuilder;

/**
 * Feature: woocommerce-product-refactor, Property 12: Variation relationship consistency
 * Validates: Requirements 6.2, 14.7
 */

// Mock WordPress and WooCommerce functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        $meta_data = [
            'product_english_name' => 'Test English Name ' . $post_id,
            '_sku' => 'TEST-SKU-' . $post_id,
            'brand' => 'Test Brand'
        ];
        return $meta_data[$key] ?? '';
    }
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy, $output = OBJECT) {
        $data = [
            'name' => 'Test Category ' . $value,
            'term_id' => rand(1, 100)
        ];
        
        if ($output === 'ARRAY_A') {
            return $data;
        }
        
        return (object) $data;
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail') {
        return ['https://example.com/image-' . $attachment_id . '.jpg', 800, 600];
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id) {
        return 'https://example.com/product/' . $post_id . '/';
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        return (object) [
            'ID' => $post_id,
            'post_excerpt' => 'Test short description for product ' . $post_id
        ];
    }
}

if (!function_exists('wc_attribute_label')) {
    function wc_attribute_label($name) {
        return ucfirst(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $name));
    }
}

if (!function_exists('wc_get_product_terms')) {
    function wc_get_product_terms($product_id, $attribute_name, $args = []) {
        return ['Test Value 1', 'Test Value 2'];
    }
}

// Mock WC_Product base class that works with the new architecture
if (!class_exists('WC_Product')) {
    class WC_Product {
        protected $id = 0;
        protected $data = [];
        
        public function __construct($product = 0) {
            if (is_numeric($product) && $product > 0) {
                $this->id = $product;
            }
        }
        
        public function get_id() { return $this->id; }
        public function get_parent_id() { return 0; }
        public function get_name() { return 'Test Product'; }
        public function get_price() { return '100.00'; }
        public function get_regular_price() { return '120.00'; }
        public function get_stock_status() { return 'instock'; }
        public function get_category_ids() { return [1]; }
        public function get_gallery_image_ids() { return [1]; }
        public function get_image_id() { return 1; }
        public function get_sku() { return 'TEST-SKU'; }
        public function get_date_created() { return new DateTime(); }
        public function get_status() { return 'publish'; }
        public function get_attributes() { return []; }
        public function get_default_attributes() { return []; }
        public function get_children() { return []; }
        public function is_type($type) { return $type === 'simple'; }
        public function exists() { return true; }
        public function get_variation_price($type = 'min') { return '100.00'; }
        public function get_variation_regular_price($type = 'min') { return '120.00'; }
        public function get_matching_variation($attributes) { return null; }
    }
}

// Enhanced mock that properly works with ProductService dependency injection
class TestWCProduct extends WC_Product {
    private $productData;

    public function __construct($data = []) {
        $this->productData = array_merge([
            'id' => rand(1, 10000),
            'parent_id' => 0,
            'name' => 'Test Product ' . uniqid(),
            'price' => '100.00',
            'regular_price' => '120.00',
            'stock_status' => 'instock',
            'category_ids' => [1],
            'gallery_image_ids' => [1],
            'image_id' => 1,
            'sku' => 'TEST-SKU-' . uniqid(),
            'date_created' => new DateTime(),
            'status' => 'publish',
            'type' => 'simple',
            'attributes' => [],
            'default_attributes' => [],
            'children' => []
        ], $data);
        
        parent::__construct($this->productData['id']);
        $this->id = $this->productData['id'];
    }

    public function get_id() { return $this->productData['id']; }
    public function get_parent_id() { return $this->productData['parent_id']; }
    public function get_name() { return $this->productData['name']; }
    public function get_price() { return $this->productData['price']; }
    public function get_regular_price() { return $this->productData['regular_price']; }
    public function get_stock_status() { return $this->productData['stock_status']; }
    public function get_category_ids() { return $this->productData['category_ids']; }
    public function get_gallery_image_ids() { return $this->productData['gallery_image_ids']; }
    public function get_image_id() { return $this->productData['image_id']; }
    public function get_sku() { return $this->productData['sku']; }
    public function get_date_created() { return $this->productData['date_created']; }
    public function get_status() { return $this->productData['status']; }
    public function get_attributes() { return $this->productData['attributes']; }
    public function get_default_attributes() { return $this->productData['default_attributes']; }
    public function get_children() { return $this->productData['children']; }
    
    public function is_type($type) { 
        if ($type === 'variation') {
            return $this->productData['parent_id'] > 0;
        }
        if ($type === 'variable') {
            return $this->productData['type'] === 'variable';
        }
        return $this->productData['type'] === $type; 
    }
    
    public function exists() { return true; }
    
    public function get_variation_price($type = 'min') {
        return $this->productData['price'];
    }
    
    public function get_variation_regular_price($type = 'min') {
        return $this->productData['regular_price'];
    }
    
    public function get_matching_variation($attributes) {
        if (!empty($this->productData['children'])) {
            return $this->productData['children'][0];
        }
        return null;
    }
}

// Global product registry for consistent testing
global $test_products;
$test_products = [];

if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id) {
        global $test_products;
        
        if (isset($test_products[$product_id])) {
            return $test_products[$product_id];
        }
        
        return null; // Return null for non-existent products
    }
}

// Mock WP_Query for getAllProducts testing
class WP_Query {
    public $posts = [];
    public $found_posts = 0;
    public $max_num_pages = 0;
    
    public function __construct($args = []) {
        global $test_products;
        
        $limit = $args['posts_per_page'] ?? 10;
        
        // Return IDs of existing test products
        $this->posts = array_keys($test_products);
        $this->found_posts = count($this->posts);
        $this->max_num_pages = ceil($this->found_posts / $limit);
        
        // Limit results
        $this->posts = array_slice($this->posts, 0, $limit);
    }
}

describe('Product Data Extraction - Property-Based Tests', function () {
    
    beforeEach(function () {
        // Clear the global product registry before each test
        global $test_products;
        $test_products = [];
    });
    
    function createProductService() {
        $configService = new ConfigService();
        $titleBuilder = new TitleBuilder($configService);
        return new ProductService($configService, $titleBuilder);
    }
    
    function createTestProduct($type = 'simple', $parentId = 0) {
        global $test_products;
        
        $productId = rand(10000, 99999); // Use high IDs to avoid conflicts
        
        $productData = [
            'id' => $productId,
            'parent_id' => $parentId,
            'type' => $type,
            'name' => 'Test Product ' . $productId,
            'price' => '100.00',
            'regular_price' => '120.00',
            'status' => 'publish'
        ];
        
        if ($type === 'variable') {
            $productData['children'] = [];
        }
        
        $product = new TestWCProduct($productData);
        $test_products[$productId] = $product;
        
        return $product;
    }
    
    test('property 12: variation relationship consistency - for any product variation, the parent_id field should correctly reference the main product ID', function () {
        $productService = createProductService();
        
        // Test 1: Simple product should have parent_id = 0
        $simpleProduct = createTestProduct('simple');
        $simpleId = $simpleProduct->get_id();
        
        $result = $productService->getProductsFromIds([$simpleId]);
        
        expect($result)->toHaveKey('products');
        expect($result['products'])->not->toBeEmpty();
        
        $productData = $result['products'][0];
        
        // Simple products should have parent_id = 0
        expect($productData->parent_id)->toBe(0);
        expect($productData->page_unique)->toBe($simpleId);
        
        // Test 2: Variable product should have parent_id = 0
        $variableProduct = createTestProduct('variable');
        $variableId = $variableProduct->get_id();
        
        $result = $productService->getProductsFromIds([$variableId]);
        
        expect($result)->toHaveKey('products');
        expect($result['products'])->not->toBeEmpty();
        
        $productData = $result['products'][0];
        
        // Variable products should have parent_id = 0
        expect($productData->parent_id)->toBe(0);
        expect($productData->page_unique)->toBe($variableId);
        
        // Test 3: Variation product should have parent_id > 0
        $parentProduct = createTestProduct('variable');
        $parentId = $parentProduct->get_id();
        
        // Create variation with proper parent relationship
        global $test_products;
        $variationId = rand(20000, 29999);
        $variationData = [
            'id' => $variationId,
            'parent_id' => $parentId,
            'type' => 'variation',
            'name' => 'Test Variation ' . $variationId,
            'price' => '90.00',
            'regular_price' => '110.00',
            'status' => 'publish'
        ];
        
        $variationProduct = new TestWCProduct($variationData);
        $test_products[$variationId] = $variationProduct;
        
        $result = $productService->getProductsFromIds([$variationId]);
        
        expect($result)->toHaveKey('products');
        expect($result['products'])->not->toBeEmpty();
        
        $productData = $result['products'][0];
        
        // Variations should have parent_id pointing to parent
        expect($productData->parent_id)->toBe($parentId);
        expect($productData->parent_id)->toBeGreaterThan(0);
        expect($productData->page_unique)->toBe($variationId);
        
    });
    
    test('variation products should maintain consistent data structure', function () {
        $productService = createProductService();
        
        // Create a parent product and variation
        $parentProduct = createTestProduct('variable');
        $parentId = $parentProduct->get_id();
        
        global $test_products;
        $variationId = rand(30000, 39999);
        $variationData = [
            'id' => $variationId,
            'parent_id' => $parentId,
            'type' => 'variation',
            'name' => 'Test Variation ' . $variationId,
            'status' => 'publish'
        ];
        
        $variationProduct = new TestWCProduct($variationData);
        $test_products[$variationId] = $variationProduct;
        
        $result = $productService->getProductsFromIds([$variationId]);
        
        expect($result)->toHaveKey('products');
        expect($result['products'])->not->toBeEmpty();
        
        $productData = $result['products'][0];
        
        // Verify all required fields exist
        $requiredFields = [
            'title', 'subtitle', 'parent_id', 'page_unique', 'current_price',
            'old_price', 'availability', 'category_name', 'image_links',
            'image_link', 'page_url', 'short_desc', 'spec', 'date',
            'registry', 'guarantee'
        ];
        
        foreach ($requiredFields as $field) {
            expect($productData)->toHaveKey($field);
        }
        
        // Verify data types
        expect($productData->parent_id)->toBeInt();
        expect($productData->page_unique)->toBeInt();
        expect($productData->title)->toBeString();
        expect($productData->subtitle)->toBeString();
        expect($productData->category_name)->toBeString();
        expect($productData->image_links)->toBeArray();
        expect($productData->page_url)->toBeString();
        expect($productData->short_desc)->toBeString();
        expect($productData->spec)->toBeArray();
        expect($productData->registry)->toBeString();
        expect($productData->guarantee)->toBeString();
        
        // Verify parent_id relationship for variations
        expect($productData->parent_id)->toBe($parentId);
        expect($productData->parent_id)->not->toBe($productData->page_unique);
    });
    
    test('product extraction should handle different product types correctly', function () {
        $productService = createProductService();
        
        // Create test products of different types
        $simpleProduct = createTestProduct('simple');
        $variableProduct = createTestProduct('variable');
        
        global $test_products;
        $variationId = rand(40000, 49999);
        $variationData = [
            'id' => $variationId,
            'parent_id' => $variableProduct->get_id(),
            'type' => 'variation',
            'name' => 'Test Variation ' . $variationId,
            'status' => 'publish'
        ];
        
        $variationProduct = new TestWCProduct($variationData);
        $test_products[$variationId] = $variationProduct;
        
        $productIds = [
            $simpleProduct->get_id(),
            $variableProduct->get_id(),
            $variationProduct->get_id()
        ];
        
        $result = $productService->getProductsFromIds($productIds);
        
        expect($result)->toHaveKey('products');
        expect($result['products'])->toHaveCount(3);
        
        foreach ($result['products'] as $index => $productData) {
            $originalId = $productIds[$index];
            
            // Verify page_unique matches the requested ID
            expect($productData->page_unique)->toBe($originalId);
            
            // Verify parent_id logic based on product type
            if ($originalId === $variationProduct->get_id()) {
                // This should be a variation
                expect($productData->parent_id)->toBe($variableProduct->get_id());
                expect($productData->parent_id)->toBeGreaterThan(0);
            } else {
                // This should be a simple or variable product
                expect($productData->parent_id)->toBe(0);
            }
        }
    });
    
    test('property-based test: parent_id consistency across multiple product types', function () {
        $productService = createProductService();
        
        global $test_products;
        $testProducts = [];
        
        // Create simple products
        for ($i = 0; $i < 3; $i++) {
            $product = createTestProduct('simple');
            $testProducts[] = [
                'product' => $product,
                'expected_parent_id' => 0,
                'type' => 'simple'
            ];
        }
        
        // Create variable products with variations
        for ($i = 0; $i < 2; $i++) {
            $parent = createTestProduct('variable');
            
            $variationId = rand(50000 + ($i * 1000), 50000 + ($i * 1000) + 999);
            $variationData = [
                'id' => $variationId,
                'parent_id' => $parent->get_id(),
                'type' => 'variation',
                'name' => 'Test Variation ' . $variationId,
                'status' => 'publish'
            ];
            
            $variation = new TestWCProduct($variationData);
            $test_products[$variationId] = $variation;
            
            $testProducts[] = [
                'product' => $parent,
                'expected_parent_id' => 0,
                'type' => 'variable'
            ];
            
            $testProducts[] = [
                'product' => $variation,
                'expected_parent_id' => $parent->get_id(),
                'type' => 'variation'
            ];
        }
        
        foreach ($testProducts as $testCase) {
            $productId = $testCase['product']->get_id();
            $result = $productService->getProductsFromIds([$productId]);
            
            expect($result)->toHaveKey('products');
            expect($result['products'])->not->toBeEmpty();
            
            $productData = $result['products'][0];
            
            // Verify parent_id matches expected value
            expect($productData->parent_id)->toBe($testCase['expected_parent_id']);
            
            // Verify page_unique always matches the requested ID
            expect($productData->page_unique)->toBe($productId);
            
            // For variations, ensure parent_id != page_unique
            if ($testCase['type'] === 'variation') {
                expect($productData->parent_id)->not->toBe($productData->page_unique);
                expect($productData->parent_id)->toBeGreaterThan(0);
            }
        }
    });
    
});