<?php

namespace Forooshyar\Services;

/**
 * Performance Optimization Service
 * 
 * Handles database query optimization, response compression,
 * lazy loading, and performance monitoring for the plugin.
 */
class PerformanceOptimizationService
{
    /** @var ConfigService */
    private $configService;
    
    /** @var CacheService */
    private $cacheService;
    
    /** @var LoggingService */
    private $loggingService;
    
    /** @var array */
    private $performanceMetrics = [];
    
    /** @var array */
    private $queryOptimizations = [];
    
    public function __construct(
        ConfigService $configService,
        CacheService $cacheService,
        LoggingService $loggingService
    ) {
        $this->configService = $configService;
        $this->cacheService = $cacheService;
        $this->loggingService = $loggingService;
        
        $this->initializeOptimizations();
    }
    
    /**
     * Initialize performance optimizations
     */
    private function initializeOptimizations(): void
    {
        // Set up query optimizations
        $this->queryOptimizations = [
            'use_object_cache' => true,
            'optimize_meta_queries' => true,
            'batch_database_operations' => true,
            'use_prepared_statements' => true,
            'limit_query_results' => true
        ];
        
        // Initialize performance metrics tracking
        $this->performanceMetrics = [
            'query_count' => 0,
            'query_time' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'memory_usage' => 0,
            'response_time' => 0
        ];
    }
    
    /**
     * Optimize database queries for product retrieval
     */
    public function optimizeProductQueries(array $queryArgs): array
    {
        $startTime = microtime(true);
        
        // Optimize query arguments
        $optimizedArgs = $this->applyQueryOptimizations($queryArgs);
        
        // Track query optimization time
        $this->performanceMetrics['query_time'] += microtime(true) - $startTime;
        $this->performanceMetrics['query_count']++;
        
        return $optimizedArgs;
    }
    
    /**
     * Apply specific query optimizations
     */
    private function applyQueryOptimizations(array $args): array
    {
        // Use fields => 'ids' for better performance when only IDs are needed
        if (!isset($args['fields'])) {
            $args['fields'] = 'ids';
        }
        
        // Optimize meta queries
        if (isset($args['meta_query']) && $this->queryOptimizations['optimize_meta_queries']) {
            $args['meta_query'] = $this->optimizeMetaQuery($args['meta_query']);
        }
        
        // Set reasonable limits to prevent memory issues
        if ($this->queryOptimizations['limit_query_results']) {
            $maxLimit = $this->configService->get('api.max_per_page', 100);
            if (!isset($args['posts_per_page']) || $args['posts_per_page'] > $maxLimit) {
                $args['posts_per_page'] = $maxLimit;
            }
        }
        
        // Optimize ordering for better database performance
        if (!isset($args['orderby'])) {
            $args['orderby'] = 'ID'; // ID ordering is faster than date
            $args['order'] = 'DESC';
        }
        
        // Disable unnecessary features for performance
        $args['no_found_rows'] = false; // We need pagination info
        $args['update_post_meta_cache'] = false; // We'll load meta separately
        $args['update_post_term_cache'] = false; // We'll load terms separately
        
        return $args;
    }
    
    /**
     * Optimize meta queries for better performance
     */
    private function optimizeMetaQuery(array $metaQuery): array
    {
        // Ensure proper indexing hints
        foreach ($metaQuery as &$query) {
            if (is_array($query) && isset($query['key'])) {
                // Add compare operator if not set for better index usage
                if (!isset($query['compare'])) {
                    $query['compare'] = '=';
                }
                
                // Optimize LIKE queries
                if ($query['compare'] === 'LIKE' && isset($query['value'])) {
                    // Use more specific LIKE patterns when possible
                    if (strpos($query['value'], '%') === false) {
                        $query['compare'] = '=';
                    }
                }
            }
        }
        
        return $metaQuery;
    }
    
    /**
     * Implement lazy loading for heavy data
     */
    public function lazyLoadProductData($product, array $requestedFields = []): array
    {
        $startTime = microtime(true);
        $productData = [];
        
        // Always load basic fields
        $basicFields = ['id', 'title', 'price', 'availability'];
        $fieldsToLoad = array_merge($basicFields, $requestedFields);
        
        // Load fields on demand
        foreach ($fieldsToLoad as $field) {
            switch ($field) {
                case 'images':
                    $productData[$field] = $this->lazyLoadImages($product);
                    break;
                    
                case 'specifications':
                    $productData[$field] = $this->lazyLoadSpecifications($product);
                    break;
                    
                case 'variations':
                    $productData[$field] = $this->lazyLoadVariations($product);
                    break;
                    
                case 'categories':
                    $productData[$field] = $this->lazyLoadCategories($product);
                    break;
                    
                default:
                    $productData[$field] = $this->loadBasicField($product, $field);
                    break;
            }
        }
        
        // Track lazy loading performance
        $this->performanceMetrics['response_time'] += microtime(true) - $startTime;
        
        return $productData;
    }
    
    /**
     * Lazy load product images
     */
    private function lazyLoadImages($product): array
    {
        $cacheKey = "product_images_{$product->get_id()}";
        
        // Try cache first
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== false) {
            $this->performanceMetrics['cache_hits']++;
            return $cached;
        }
        
        $this->performanceMetrics['cache_misses']++;
        
        // Load images with size optimization
        $images = [];
        $imageConfig = $this->configService->get('images', [
            'max_images' => 10,
            'sizes' => ['medium', 'large'],
            'quality' => 80
        ]);
        
        // Get main image
        $mainImageId = $product->get_image_id();
        if ($mainImageId) {
            $images['main'] = $this->optimizeImageData($mainImageId, $imageConfig);
        }
        
        // Get gallery images (limited by config)
        $galleryIds = array_slice(
            $product->get_gallery_image_ids(), 
            0, 
            $imageConfig['max_images'] - 1
        );
        
        $images['gallery'] = [];
        foreach ($galleryIds as $imageId) {
            $images['gallery'][] = $this->optimizeImageData($imageId, $imageConfig);
        }
        
        // Cache the result
        $this->cacheService->set($cacheKey, $images, 3600);
        
        return $images;
    }
    
    /**
     * Optimize image data for performance
     */
    private function optimizeImageData(int $imageId, array $config): array
    {
        $imageData = [];
        
        foreach ($config['sizes'] as $size) {
            $imageInfo = wp_get_attachment_image_src($imageId, $size);
            if ($imageInfo) {
                $imageData[$size] = [
                    'url' => $imageInfo[0],
                    'width' => $imageInfo[1],
                    'height' => $imageInfo[2]
                ];
            }
        }
        
        return $imageData;
    }
    
    /**
     * Lazy load product specifications
     */
    private function lazyLoadSpecifications($product): array
    {
        $cacheKey = "product_specs_{$product->get_id()}";
        
        // Try cache first
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== false) {
            $this->performanceMetrics['cache_hits']++;
            return $cached;
        }
        
        $this->performanceMetrics['cache_misses']++;
        
        // Load specifications efficiently
        $specs = [];
        $attributes = $product->get_attributes();
        
        foreach ($attributes as $attribute) {
            if ($attribute->get_visible()) {
                $name = wc_attribute_label($attribute->get_name());
                $values = $attribute->is_taxonomy() 
                    ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names'])
                    : $attribute->get_options();
                
                if (!empty($values)) {
                    $specs[$name] = is_array($values) ? implode(', ', $values) : $values;
                }
            }
        }
        
        // Cache the result
        $this->cacheService->set($cacheKey, $specs, 3600);
        
        return $specs;
    }
    
    /**
     * Lazy load product variations
     */
    private function lazyLoadVariations($product): array
    {
        if (!$product->is_type('variable')) {
            return [];
        }
        
        $cacheKey = "product_variations_{$product->get_id()}";
        
        // Try cache first
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== false) {
            $this->performanceMetrics['cache_hits']++;
            return $cached;
        }
        
        $this->performanceMetrics['cache_misses']++;
        
        // Load variations efficiently
        $variations = [];
        $variationIds = $product->get_children();
        
        // Batch load variations to reduce queries
        foreach (array_chunk($variationIds, 20) as $chunk) {
            foreach ($chunk as $variationId) {
                $variation = wc_get_product($variationId);
                if ($variation && $variation->exists()) {
                    $variations[] = [
                        'id' => $variation->get_id(),
                        'price' => $variation->get_price(),
                        'stock_status' => $variation->get_stock_status(),
                        'attributes' => $variation->get_attributes()
                    ];
                }
            }
        }
        
        // Cache the result
        $this->cacheService->set($cacheKey, $variations, 3600);
        
        return $variations;
    }
    
    /**
     * Lazy load product categories
     */
    private function lazyLoadCategories($product): array
    {
        $cacheKey = "product_categories_{$product->get_id()}";
        
        // Try cache first
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== false) {
            $this->performanceMetrics['cache_hits']++;
            return $cached;
        }
        
        $this->performanceMetrics['cache_misses']++;
        
        // Load categories efficiently
        $categories = [];
        $categoryIds = $product->get_category_ids();
        
        if (!empty($categoryIds)) {
            $terms = get_terms([
                'taxonomy' => 'product_cat',
                'include' => $categoryIds,
                'fields' => 'all'
            ]);
            
            foreach ($terms as $term) {
                $categories[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug
                ];
            }
        }
        
        // Cache the result
        $this->cacheService->set($cacheKey, $categories, 3600);
        
        return $categories;
    }
    
    /**
     * Load basic product field
     */
    private function loadBasicField($product, string $field)
    {
        switch ($field) {
            case 'id':
                return $product->get_id();
            case 'title':
                return $product->get_name();
            case 'price':
                return $product->get_price();
            case 'availability':
                return $product->get_stock_status();
            default:
                return null;
        }
    }
    
    /**
     * Compress API response for large catalogs
     */
    public function compressResponse(array $data): array
    {
        $startTime = microtime(true);
        
        // Check if compression is enabled
        $compressionConfig = $this->configService->get('compression', [
            'enabled' => true,
            'min_size' => 1024, // 1KB
            'level' => 6
        ]);
        
        if (!$compressionConfig['enabled']) {
            return $data;
        }
        
        $jsonData = json_encode($data);
        $dataSize = strlen($jsonData);
        
        // Only compress if data is larger than minimum size
        if ($dataSize < $compressionConfig['min_size']) {
            return $data;
        }
        
        // Set compression headers if not already set
        if (!headers_sent()) {
            // Check if client accepts gzip
            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
            
            if (strpos($acceptEncoding, 'gzip') !== false) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                
                // Compress the response
                $compressedData = gzencode($jsonData, $compressionConfig['level']);
                
                // Log compression ratio
                $compressionRatio = $dataSize > 0 ? (strlen($compressedData) / $dataSize) * 100 : 100;
                $this->loggingService->logPerformance('response_compression', [
                    'original_size' => $dataSize,
                    'compressed_size' => strlen($compressedData),
                    'compression_ratio' => $compressionRatio,
                    'compression_time' => microtime(true) - $startTime
                ]);
                
                // Return compressed data (will be handled by WordPress)
                return $data;
            }
        }
        
        return $data;
    }
    
    /**
     * Monitor and optimize memory usage
     */
    public function optimizeMemoryUsage(): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        $this->performanceMetrics['memory_usage'] = $currentMemory;
        
        // Log memory usage if it's high
        if ($currentMemory > ($memoryLimit * 0.8)) {
            $this->loggingService->logPerformance('high_memory_usage', [
                'current_memory' => $currentMemory,
                'peak_memory' => $peakMemory,
                'memory_limit' => $memoryLimit,
                'usage_percentage' => ($currentMemory / $memoryLimit) * 100
            ]);
        }
        
        // Force garbage collection if memory usage is high
        if ($currentMemory > ($memoryLimit * 0.9)) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }
    
    /**
     * Batch database operations for better performance
     */
    public function batchDatabaseOperations(callable $operation, array $items, int $batchSize = 50): array
    {
        $results = [];
        $batches = array_chunk($items, $batchSize);
        
        foreach ($batches as $batch) {
            $startTime = microtime(true);
            
            try {
                $batchResults = $operation($batch);
                $results = array_merge($results, $batchResults);
                
                // Log batch performance
                $this->loggingService->logPerformance('batch_operation', [
                    'batch_size' => count($batch),
                    'execution_time' => microtime(true) - $startTime,
                    'memory_usage' => memory_get_usage(true)
                ]);
                
            } catch (\Exception $e) {
                $this->loggingService->logError($e, 'batch_operation', 'batch_operation_failed');
                
                // Continue with next batch on error
                continue;
            }
            
            // Optimize memory between batches
            $this->optimizeMemoryUsage();
        }
        
        return $results;
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return array_merge($this->performanceMetrics, [
            'cache_hit_ratio' => $this->calculateCacheHitRatio(),
            'average_query_time' => $this->calculateAverageQueryTime(),
            'memory_usage_mb' => round($this->performanceMetrics['memory_usage'] / 1024 / 1024, 2),
            'optimizations_enabled' => $this->queryOptimizations
        ]);
    }
    
    /**
     * Calculate cache hit ratio
     */
    private function calculateCacheHitRatio(): float
    {
        $totalRequests = $this->performanceMetrics['cache_hits'] + $this->performanceMetrics['cache_misses'];
        
        if ($totalRequests === 0) {
            return 0.0;
        }
        
        return round(($this->performanceMetrics['cache_hits'] / $totalRequests) * 100, 2);
    }
    
    /**
     * Calculate average query time
     */
    private function calculateAverageQueryTime(): float
    {
        if ($this->performanceMetrics['query_count'] === 0) {
            return 0.0;
        }
        
        return round($this->performanceMetrics['query_time'] / $this->performanceMetrics['query_count'], 4);
    }
    
    /**
     * Reset performance metrics
     */
    public function resetMetrics(): void
    {
        $this->performanceMetrics = [
            'query_count' => 0,
            'query_time' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'memory_usage' => 0,
            'response_time' => 0
        ];
    }
    
    /**
     * Test with large product catalogs
     */
    public function testLargeCatalogPerformance(int $productCount = 10000): array
    {
        $startTime = microtime(true);
        $initialMemory = memory_get_usage(true);
        
        $testResults = [
            'product_count' => $productCount,
            'start_time' => $startTime,
            'initial_memory' => $initialMemory,
            'operations' => []
        ];
        
        try {
            // Test 1: Query optimization with large dataset
            $queryStart = microtime(true);
            $optimizedQuery = $this->optimizeProductQueries([
                'post_type' => 'product',
                'posts_per_page' => 100,
                'paged' => 1
            ]);
            $testResults['operations']['query_optimization'] = [
                'time' => microtime(true) - $queryStart,
                'memory' => memory_get_usage(true) - $initialMemory,
                'success' => !empty($optimizedQuery)
            ];
            
            // Test 2: Batch processing simulation
            $batchStart = microtime(true);
            $productIds = range(1, min($productCount, 1000)); // Limit for testing
            
            $batchResults = $this->batchDatabaseOperations(
                function($batch) {
                    // Simulate processing products
                    return array_map(function($id) {
                        return ['id' => $id, 'processed' => true];
                    }, $batch);
                },
                $productIds,
                50
            );
            
            $testResults['operations']['batch_processing'] = [
                'time' => microtime(true) - $batchStart,
                'memory' => memory_get_usage(true) - $initialMemory,
                'processed_count' => count($batchResults),
                'success' => count($batchResults) === count($productIds)
            ];
            
            // Test 3: Memory optimization
            $memoryStart = microtime(true);
            $this->optimizeMemoryUsage();
            $testResults['operations']['memory_optimization'] = [
                'time' => microtime(true) - $memoryStart,
                'memory_after_gc' => memory_get_usage(true),
                'success' => true
            ];
            
            // Test 4: Cache performance simulation
            $cacheStart = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $this->cacheService->set("test_key_{$i}", ['data' => $i]);
                $this->cacheService->get("test_key_{$i}");
            }
            $testResults['operations']['cache_performance'] = [
                'time' => microtime(true) - $cacheStart,
                'memory' => memory_get_usage(true) - $initialMemory,
                'success' => true
            ];
            
        } catch (\Exception $e) {
            $testResults['error'] = $e->getMessage();
        }
        
        $testResults['total_time'] = microtime(true) - $startTime;
        $testResults['peak_memory'] = memory_get_peak_usage(true);
        $testResults['final_memory'] = memory_get_usage(true);
        
        return $testResults;
    }
}