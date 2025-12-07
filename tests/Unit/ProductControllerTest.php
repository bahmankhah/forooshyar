<?php

use Forooshyar\Controllers\ProductController;
use Forooshyar\Services\ProductService;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\ConfigService;

// Mock WordPress REST API classes if not already defined
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $method = 'GET';
        
        public function __construct($method = 'GET') {
            $this->method = $method;
        }
        
        public function get_params() {
            return $this->params;
        }
        
        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }
        
        public function get_param($key) {
            return $this->params[$key] ?? null;
        }
        
        public function get_method() {
            return $this->method;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        
        public function get_data() {
            return $this->data;
        }
        
        public function get_status() {
            return $this->status;
        }
        
        public function set_data($data) {
            $this->data = $data;
        }
        
        public function set_status($status) {
            $this->status = $status;
        }
    }
}

describe('ProductController', function () {
    
    beforeEach(function () {
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
        
        if (!function_exists('error_log')) {
            function error_log($message) {
                // Silent for tests
            }
        }
    });

    test('index method should return products with pagination', function () {
        // Create mock services
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Mock ProductService
        $productService = new class extends ProductService {
            public function __construct() {
                // Skip parent constructor
            }
            
            public function getProducts($args) {
                return [
                    'count' => 2,
                    'max_pages' => 1,
                    'products' => [
                        (object) [
                            'title' => 'Test Product 1',
                            'page_unique' => 1,
                            'parent_id' => 0,
                            'current_price' => '100',
                            'old_price' => '120'
                        ],
                        (object) [
                            'title' => 'Test Product 2',
                            'page_unique' => 2,
                            'parent_id' => 0,
                            'current_price' => '200',
                            'old_price' => '220'
                        ]
                    ]
                ];
            }
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        $request = new WP_REST_Request('GET');
        $request->set_param('page', 1);
        $request->set_param('limit', 10);
        
        $response = $controller->index($request);
        
        expect($response)->toBeInstanceOf(WP_REST_Response::class);
        expect($response->get_status())->toBe(200);
        
        $data = $response->get_data();
        expect($data)->toHaveKey('count');
        expect($data)->toHaveKey('max_pages');
        expect($data)->toHaveKey('products');
        expect($data['count'])->toBe(2);
        expect(count($data['products']))->toBe(2);
    });
    
    test('show method should return single product', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Mock ProductService
        $productService = new class extends ProductService {
            public function __construct() {
                // Skip parent constructor
            }
            
            public function getProducts($args) {
                if (isset($args['page_unique']) && $args['page_unique'] === 123) {
                    return [
                        'count' => 1,
                        'max_pages' => 1,
                        'products' => [
                            (object) [
                                'title' => 'Single Test Product',
                                'page_unique' => 123,
                                'parent_id' => 0,
                                'current_price' => '150'
                            ]
                        ]
                    ];
                }
                return ['count' => 0, 'max_pages' => 0, 'products' => []];
            }
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        $request = new WP_REST_Request('GET');
        $request->set_param('id', 123);
        
        $response = $controller->show($request);
        
        expect($response->get_status())->toBe(200);
        
        $data = $response->get_data();
        expect($data)->toHaveKey('title');
        expect($data['title'])->toBe('Single Test Product');
        expect($data['page_unique'])->toBe(123);
    });
    
    test('show method should return 404 for non-existent product', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Mock ProductService that returns empty results
        $productService = new class extends ProductService {
            public function __construct() {
                // Skip parent constructor
            }
            
            public function getProducts($args) {
                return ['count' => 0, 'max_pages' => 0, 'products' => []];
            }
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        $request = new WP_REST_Request('GET');
        $request->set_param('id', 999);
        
        $response = $controller->show($request);
        
        expect($response->get_status())->toBe(404);
        
        $data = $response->get_data();
        expect($data)->toHaveKey('success');
        expect($data['success'])->toBeFalse();
        expect($data)->toHaveKey('error');
    });
    
    test('getByIds method should return products for valid IDs', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Mock ProductService
        $productService = new class extends ProductService {
            public function __construct() {
                // Skip parent constructor
            }
            
            public function getProductsFromIds($ids) {
                $products = [];
                foreach ($ids as $id) {
                    if ($id <= 100) { // Mock: only IDs <= 100 exist
                        $products[] = (object) [
                            'title' => "Product {$id}",
                            'page_unique' => $id,
                            'parent_id' => 0
                        ];
                    }
                }
                return ['products' => $products];
            }
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        $request = new WP_REST_Request('POST');
        $request->set_param('ids', [1, 2, 3]);
        
        $response = $controller->getByIds($request);
        
        expect($response->get_status())->toBe(200);
        
        $data = $response->get_data();
        expect($data)->toHaveKey('products');
        expect(count($data['products']))->toBe(3);
    });
    
    test('getByIds method should return error for invalid input', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        $productService = new class extends ProductService {
            public function __construct() {}
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        $request = new WP_REST_Request('POST');
        $request->set_param('ids', 'not-an-array');
        
        $response = $controller->getByIds($request);
        
        expect($response->get_status())->toBe(400);
        
        $data = $response->get_data();
        expect($data['success'])->toBeFalse();
        expect($data)->toHaveKey('error');
    });
    
    test('getBySlugs method should return products for valid slugs', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        
        // Mock ProductService
        $productService = new class extends ProductService {
            public function __construct() {
                // Skip parent constructor
            }
            
            public function getProductsFromSlugs($slugs) {
                $products = [];
                foreach ($slugs as $slug) {
                    $products[] = (object) [
                        'title' => ucfirst(str_replace('-', ' ', $slug)),
                        'page_unique' => rand(1, 1000),
                        'parent_id' => 0
                    ];
                }
                return ['products' => $products];
            }
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        $request = new WP_REST_Request('POST');
        $request->set_param('slugs', ['test-product', 'another-product']);
        
        $response = $controller->getBySlugs($request);
        
        expect($response->get_status())->toBe(200);
        
        $data = $response->get_data();
        expect($data)->toHaveKey('products');
        expect(count($data['products']))->toBe(2);
    });
    
    test('index method should validate parameters correctly', function () {
        $configService = new ConfigService();
        $cacheService = new CacheService($configService);
        $productService = new class extends ProductService {
            public function __construct() {}
        };
        
        $controller = new ProductController($productService, $cacheService);
        
        // Test invalid page parameter
        $request = new WP_REST_Request('GET');
        $request->set_param('page', -1);
        
        $response = $controller->index($request);
        
        expect($response->get_status())->toBe(400);
        
        $data = $response->get_data();
        expect($data['success'])->toBeFalse();
        expect($data['error']['code'])->toBe('INVALID_PARAMETERS');
    });
});