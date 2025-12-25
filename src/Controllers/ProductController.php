<?php

namespace Forooshyar\Controllers;

use Forooshyar\Services\ProductService;
use Forooshyar\Services\CacheService;
use Forooshyar\Services\ErrorHandlingService;
use Forooshyar\Resources\ProductResource;
use Forooshyar\Resources\ProductCollectionResource;
use WP_REST_Request;
use WP_REST_Response;

class ProductController extends Controller
{
    /** @var ProductService */
    private $productService;
    
    /** @var CacheService */
    private $cacheService;
    
    /** @var ErrorHandlingService */
    private $errorHandlingService;
    

    public function __construct(
        ProductService $productService = null, 
        CacheService $cacheService = null, 
        ErrorHandlingService $errorHandlingService = null
    ) {
        // Allow dependency injection for testing, but create instances if not provided
        $this->productService = $productService ?? $this->createProductService();
        $this->cacheService = $cacheService ?? $this->createCacheService();
        $this->errorHandlingService = $errorHandlingService ?? $this->createErrorHandlingService();
    }

    /**
     * Get paginated list of products
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handleIndex($request);
    }

    /**
     * Handle the actual index request
     */
    private function handleIndex(WP_REST_Request $request): WP_REST_Response
    {
        // Validate parameters first
        try {
            $params = $this->validateRequest($request);
        } catch (\InvalidArgumentException $e) {
            $errorResponse = $this->errorHandlingService->getErrorResponse($e, 'validate_index_request');
            return new WP_REST_Response($errorResponse, 400);
        }
        
        // Generate cache key for this request
        $cacheKey = $this->buildCacheKey('products_list', $params);
        
        // Execute with comprehensive error handling and fallback
        $result = $this->errorHandlingService->executeDatabaseWithCacheFallback(
            function() use ($params, $cacheKey) {
                // Try cache first
                $cachedData = $this->cacheService->get($cacheKey);
                if ($cachedData !== false) {
                    return $cachedData;
                }
                
                // Get products from service with timeout protection
                $productData = $this->errorHandlingService->executeWithTimeout(
                    function() use ($params) {
                        return $this->productService->getProducts($params);
                    },
                    30,
                    'get_products_list'
                );
                
                // Transform using ProductCollectionResource
                $collection = ProductCollectionResource::fromArray($productData);
                $responseData = $collection->toArray();
                
                // Cache the result for future requests
                $this->cacheService->set($cacheKey, $responseData);
                
                // Also store as fallback cache
                $this->cacheService->set($cacheKey . '_fallback', $responseData, 86400); // 24 hours
                
                return $responseData;
            },
            $cacheKey,
            'products_index'
        );
        
        if ($result['success']) {
            return new WP_REST_Response($result['data'], 200);
        } else {
            $statusCode = $this->getHttpStatusFromError($result['error']);
            return new WP_REST_Response($result, $statusCode);
        }
    }

    /**
     * Get single product by ID
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function show(WP_REST_Request $request): WP_REST_Response
    {
            return $this->handleShow($request);
    }

    /**
     * Handle the actual show request
     */
    private function handleShow(WP_REST_Request $request): WP_REST_Response
    {
        $productId = $request->get_param('id');
        
        // Validate product ID
        if (!$productId || !is_numeric($productId)) {
            $errorResponse = $this->errorHandlingService->getErrorResponse(
                new \InvalidArgumentException('شناسه محصول باید عددی باشد'),
                'validate_product_id'
            );
            return new WP_REST_Response($errorResponse, 400);
        }
        
        $productId = (int) $productId;
        $cacheKey = $this->buildCacheKey('product', ['id' => $productId]);
        
        // Execute with comprehensive error handling
        $result = $this->errorHandlingService->executeDatabaseWithCacheFallback(
            function() use ($productId, $cacheKey) {
                // Try cache first
                $cachedData = $this->cacheService->get($cacheKey);
                if ($cachedData !== false) {
                    return $cachedData;
                }
                
                // Get product from service with timeout protection
                $params = [
                    'page_unique' => $productId,
                    'limit' => 1,
                    'page' => 1
                ];
                
                $productData = $this->errorHandlingService->executeWithTimeout(
                    function() use ($params) {
                        return $this->productService->getProducts($params);
                    },
                    15,
                    'get_single_product'
                );
                
                if (empty($productData['products'])) {
                    throw new \Exception("محصول با شناسه {$productId} وجود ندارد");
                }
                
                // Transform single product
                $product = $productData['products'][0];
                $resource = new ProductResource($product);
                $responseData = $resource->toArray();
                
                // Cache the result
                $this->cacheService->set($cacheKey, $responseData);
                
                // Store fallback cache
                $this->cacheService->set($cacheKey . '_fallback', $responseData, 86400);
                
                return $responseData;
            },
            $cacheKey,
            'product_show'
        );
        
        if ($result['success']) {
            return new WP_REST_Response($result['data'], 200);
        } else {
            // Check if this is a "not found" error
            if (strpos($result['error']['message'] ?? '', 'وجود ندارد') !== false) {
                return new WP_REST_Response($result, 404);
            }
            
            $statusCode = $this->getHttpStatusFromError($result['error']);
            return new WP_REST_Response($result, $statusCode);
        }
    }

    /**
     * Get products by array of IDs
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getByIds(WP_REST_Request $request): WP_REST_Response
    {
            return $this->handleGetByIds($request);
    }

    /**
     * Handle the actual getByIds request
     */
    private function handleGetByIds(WP_REST_Request $request): WP_REST_Response
    {
        $ids = $request->get_param('ids');
        
        // Validate IDs array
        if (!is_array($ids) || empty($ids)) {
            $errorResponse = $this->errorHandlingService->getErrorResponse(
                new \InvalidArgumentException('آرایه‌ای از شناسه‌های عددی ارسال کنید'),
                'validate_ids_array'
            );
            return new WP_REST_Response($errorResponse, 400);
        }
        
        // Validate and sanitize IDs
        $validIds = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $validIds[] = (int) $id;
            }
        }
        
        if (empty($validIds)) {
            $errorResponse = $this->errorHandlingService->getErrorResponse(
                new \InvalidArgumentException('حداقل یک شناسه عددی معتبر ارسال کنید'),
                'validate_valid_ids'
            );
            return new WP_REST_Response($errorResponse, 400);
        }
        
        // Limit number of IDs to prevent abuse and memory issues
        if (count($validIds) > 100) {
            $validIds = array_slice($validIds, 0, 100);
        }
        
        $cacheKey = $this->buildCacheKey('products_by_ids', ['ids' => $validIds]);
        
        // Execute with comprehensive error handling
        $result = $this->errorHandlingService->executeDatabaseWithCacheFallback(
            function() use ($validIds, $cacheKey) {
                // Check memory usage before processing
                if (!$this->errorHandlingService->checkMemoryUsage()) {
                    throw new \Exception('حافظه سیستم کافی نیست برای پردازش این تعداد محصول');
                }
                
                // Try cache first
                $cachedData = $this->cacheService->get($cacheKey);
                if ($cachedData !== false) {
                    return $cachedData;
                }
                
                // Get products from service with timeout protection
                $productData = $this->errorHandlingService->executeWithTimeout(
                    function() use ($validIds) {
                        return $this->productService->getProductsFromIds($validIds);
                    },
                    45, // Longer timeout for multiple products
                    'get_products_by_ids'
                );
                
                // Transform using ProductResource collection
                $products = isset($productData['products']) ? $productData['products'] : [];
                $transformedProducts = [];
                
                foreach ($products as $product) {
                    $resource = new ProductResource($product);
                    $transformedProducts[] = $resource->toArray();
                }
                
                $responseData = [
                    'products' => $transformedProducts
                ];
                
                // Cache the result
                $this->cacheService->set($cacheKey, $responseData);
                
                // Store fallback cache
                $this->cacheService->set($cacheKey . '_fallback', $responseData, 86400);
                
                return $responseData;
            },
            $cacheKey,
            'products_by_ids'
        );
        
        if ($result['success']) {
            return new WP_REST_Response($result['data'], 200);
        } else {
            $statusCode = $this->getHttpStatusFromError($result['error']);
            return new WP_REST_Response($result, $statusCode);
        }
    }

    /**
     * Get products by array of slugs
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getBySlugs(WP_REST_Request $request): WP_REST_Response
    {
            return $this->handleGetBySlugs($request);
    }

    /**
     * Handle the actual getBySlugs request
     */
    private function handleGetBySlugs(WP_REST_Request $request): WP_REST_Response
    {
        $slugs = $request->get_param('slugs');
        
        // Validate slugs array
        if (!is_array($slugs) || empty($slugs)) {
            $errorResponse = $this->errorHandlingService->getErrorResponse(
                new \InvalidArgumentException('آرایه‌ای از نام‌های محصول ارسال کنید'),
                'validate_slugs_array'
            );
            return new WP_REST_Response($errorResponse, 400);
        }
        
        // Validate and sanitize slugs
        $validSlugs = [];
        foreach ($slugs as $slug) {
            if (is_string($slug) && !empty(trim($slug))) {
                $validSlugs[] = sanitize_title($slug);
            }
        }
        
        if (empty($validSlugs)) {
            $errorResponse = $this->errorHandlingService->getErrorResponse(
                new \InvalidArgumentException('حداقل یک نام محصول معتبر ارسال کنید'),
                'validate_valid_slugs'
            );
            return new WP_REST_Response($errorResponse, 400);
        }
        
        // Limit number of slugs to prevent abuse
        if (count($validSlugs) > 50) {
            $validSlugs = array_slice($validSlugs, 0, 50);
        }
        
        $cacheKey = $this->buildCacheKey('products_by_slugs', ['slugs' => $validSlugs]);
        
        // Execute with comprehensive error handling
        $result = $this->errorHandlingService->executeDatabaseWithCacheFallback(
            function() use ($validSlugs, $cacheKey) {
                // Try cache first
                $cachedData = $this->cacheService->get($cacheKey);
                if ($cachedData !== false) {
                    return $cachedData;
                }
                
                // Get products from service with timeout protection
                $productData = $this->errorHandlingService->executeWithTimeout(
                    function() use ($validSlugs) {
                        return $this->productService->getProductsFromSlugs($validSlugs);
                    },
                    30,
                    'get_products_by_slugs'
                );
                
                // Transform using ProductResource collection
                $products = isset($productData['products']) ? $productData['products'] : [];
                $transformedProducts = [];
                
                foreach ($products as $product) {
                    $resource = new ProductResource($product);
                    $transformedProducts[] = $resource->toArray();
                }
                
                $responseData = [
                    'products' => $transformedProducts
                ];
                
                // Cache the result
                $this->cacheService->set($cacheKey, $responseData);
                
                // Store fallback cache
                $this->cacheService->set($cacheKey . '_fallback', $responseData, 86400);
                
                return $responseData;
            },
            $cacheKey,
            'products_by_slugs'
        );
        
        if ($result['success']) {
            return new WP_REST_Response($result['data'], 200);
        } else {
            $statusCode = $this->getHttpStatusFromError($result['error']);
            return new WP_REST_Response($result, $statusCode);
        }
    }

    /**
     * Validate request parameters
     * 
     * @param WP_REST_Request $request
     * @return array
     * @throws \InvalidArgumentException
     */
    private function validateRequest(WP_REST_Request $request): array
    {
        $params = [];
        
        // Page parameter
        $page = $request->get_param('page');
        if ($page !== null) {
            if (!is_numeric($page) || $page < 1) {
                throw new \InvalidArgumentException('شماره صفحه باید عددی مثبت باشد');
            }
            $params['page'] = (int) $page;
        } else {
            $params['page'] = 1;
        }
        
        // Limit parameter
        $limit = $request->get_param('limit');
        if ($limit !== null) {
            if (!is_numeric($limit) || $limit < 1 || $limit > 1000) {
                throw new \InvalidArgumentException('حد محصولات باید بین 1 تا 1000 باشد');
            }
            $params['limit'] = (int) $limit;
        } else {
            $params['limit'] = 100;
        }
        
        // Show variations parameter
        $showVariations = $request->get_param('show_variations');
        if ($showVariations !== null) {
            $params['show_variations'] = filter_var($showVariations, FILTER_VALIDATE_BOOLEAN);
        } else {
            $params['show_variations'] = true;
        }
        
        // Page unique parameter (for single product)
        $pageUnique = $request->get_param('page_unique');
        if ($pageUnique !== null) {
            if (!is_numeric($pageUnique)) {
                throw new \InvalidArgumentException('شناسه منحصر به فرد صفحه باید عددی باشد');
            }
            $params['page_unique'] = (int) $pageUnique;
        }
        
        // Page URL parameter (for single product by URL)
        $pageUrl = $request->get_param('page_url');
        if ($pageUrl !== null) {
            if (!is_string($pageUrl) || empty(trim($pageUrl))) {
                throw new \InvalidArgumentException('آدرس صفحه نامعتبر است');
            }
            $params['page_url'] = sanitize_url($pageUrl);
        }
        
        return $params;
    }

    /**
     * Build cache key from prefix and parameters
     * 
     * @param string $prefix
     * @param array $params
     * @return string
     */
    private function buildCacheKey(string $prefix, array $params): string
    {
        return $this->cacheService->generateKey($prefix, $params);
    }

    /**
     * Create ProductService instance
     * 
     * @return ProductService
     */
    private function createProductService(): ProductService
    {
        // In a real application, this would use dependency injection
        // For now, we'll create the dependencies manually
        $configService = new \Forooshyar\Services\ConfigService();
        $titleBuilder = new \Forooshyar\Services\TitleBuilder($configService);
        
        return new ProductService($configService, $titleBuilder);
    }

    /**
     * Create CacheService instance
     * 
     * @return CacheService
     */
    private function createCacheService(): CacheService
    {
        $configService = new \Forooshyar\Services\ConfigService();
        return new CacheService($configService);
    }

    /**
     * Create ErrorHandlingService instance
     * 
     * @return ErrorHandlingService
     */
    private function createErrorHandlingService(): ErrorHandlingService
    {
        $configService = new \Forooshyar\Services\ConfigService();
        $cacheService = new CacheService($configService);
        $loggingService = new \Forooshyar\Services\LoggingService($configService);
        
        return new ErrorHandlingService($configService, $cacheService, $loggingService);
    }

    /**
     * Get HTTP status code from error information
     */
    private function getHttpStatusFromError(array $error): int
    {
        $code = $error['code'] ?? 'INTERNAL_ERROR';
        
        switch ($code) {
            case 'INVALID_PARAMETERS':
            case 'INVALID_INPUT':
            case 'INVALID_IDS':
            case 'INVALID_SLUGS':
            case 'NO_VALID_IDS':
            case 'NO_VALID_SLUGS':
                return 400;
                
            case 'PRODUCT_NOT_FOUND':
                return 404;
                
            case 'OPERATION_TIMEOUT':
                return 408;
                
            case 'MEMORY_LIMIT_EXCEEDED':
                return 413;
                
            case 'CIRCUIT_BREAKER_OPEN':
                return 503;
                
            default:
                return 500;
        }
    }
}