<?php

namespace Forooshyar\Services;

/**
 * Enhanced cache invalidation service with error handling and recovery
 */
class CacheInvalidationService
{
    private CacheService $cacheService;
    private ErrorHandlingService $errorHandlingService;
    private LoggingService $loggingService;
    
    public function __construct(
        CacheService $cacheService,
        ErrorHandlingService $errorHandlingService,
        LoggingService $loggingService
    ) {
        $this->cacheService = $cacheService;
        $this->errorHandlingService = $errorHandlingService;
        $this->loggingService = $loggingService;
        
        $this->registerWooCommerceHooks();
    }

    /**
     * Register WooCommerce hooks for automatic cache invalidation
     */
    private function registerWooCommerceHooks(): void
    {
        // Product save/update hooks
        add_action('woocommerce_update_product', [$this, 'handleProductUpdate'], 10, 1);
        add_action('woocommerce_new_product', [$this, 'handleProductUpdate'], 10, 1);
        
        // Product delete hooks
        add_action('before_delete_post', [$this, 'handleProductDelete'], 10, 1);
        add_action('wp_trash_post', [$this, 'handleProductDelete'], 10, 1);
        
        // Variation hooks
        add_action('woocommerce_save_product_variation', [$this, 'handleVariationUpdate'], 10, 2);
        add_action('woocommerce_delete_product_variation', [$this, 'handleVariationDelete'], 10, 1);
        
        // Category hooks
        add_action('edited_product_cat', [$this, 'handleCategoryUpdate'], 10, 1);
        add_action('delete_product_cat', [$this, 'handleCategoryDelete'], 10, 1);
        
        // Stock change hooks
        add_action('woocommerce_product_set_stock', [$this, 'handleStockChange'], 10, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'handleStockChange'], 10, 1);
        
        // Bulk operations
        add_action('woocommerce_bulk_edit_variations', [$this, 'handleBulkVariationUpdate'], 10, 4);
    }

    /**
     * Handle product update with error recovery
     */
    public function handleProductUpdate(int $productId): void
    {
        $this->errorHandlingService->executeWithFallback(
            function() use ($productId) {
                return $this->cacheService->invalidateProduct($productId);
            },
            function() use ($productId) {
                // Fallback: log the invalidation request for later processing
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for product {$productId}"),
                    'cache',
                    'product_update_invalidation'
                );
                return false;
            },
            'product_update_cache_invalidation'
        );
    }

    /**
     * Handle product deletion with error recovery
     */
    public function handleProductDelete(int $postId): void
    {
        // Check if this is a product
        if (get_post_type($postId) !== 'product') {
            return;
        }
        
        $this->errorHandlingService->executeWithFallback(
            function() use ($postId) {
                return $this->cacheService->invalidateProduct($postId);
            },
            function() use ($postId) {
                // Fallback: log the invalidation request
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for deleted product {$postId}"),
                    'cache',
                    'product_delete_invalidation'
                );
                return false;
            },
            'product_delete_cache_invalidation'
        );
    }

    /**
     * Handle variation update with error recovery
     */
    public function handleVariationUpdate(int $variationId, int $loop): void
    {
        $this->errorHandlingService->executeWithFallback(
            function() use ($variationId) {
                // Invalidate both variation and parent product
                $variation = wc_get_product($variationId);
                if ($variation && $variation->is_type('variation')) {
                    $parentId = $variation->get_parent_id();
                    
                    $success = $this->cacheService->invalidateProduct($variationId);
                    if ($parentId) {
                        $success = $this->cacheService->invalidateProduct($parentId) && $success;
                    }
                    
                    return $success;
                }
                return false;
            },
            function() use ($variationId) {
                // Fallback: log the invalidation request
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for variation {$variationId}"),
                    'cache',
                    'variation_update_invalidation'
                );
                return false;
            },
            'variation_update_cache_invalidation'
        );
    }

    /**
     * Handle variation deletion with error recovery
     */
    public function handleVariationDelete(int $variationId): void
    {
        $this->errorHandlingService->executeWithFallback(
            function() use ($variationId) {
                // Get parent ID before deletion
                $variation = wc_get_product($variationId);
                $parentId = $variation ? $variation->get_parent_id() : null;
                
                $success = $this->cacheService->invalidateProduct($variationId);
                if ($parentId) {
                    $success = $this->cacheService->invalidateProduct($parentId) && $success;
                }
                
                return $success;
            },
            function() use ($variationId) {
                // Fallback: log the invalidation request
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for deleted variation {$variationId}"),
                    'cache',
                    'variation_delete_invalidation'
                );
                return false;
            },
            'variation_delete_cache_invalidation'
        );
    }

    /**
     * Handle category update with error recovery
     */
    public function handleCategoryUpdate(int $categoryId): void
    {
        $this->errorHandlingService->executeWithFallback(
            function() use ($categoryId) {
                return $this->cacheService->invalidateCategory($categoryId);
            },
            function() use ($categoryId) {
                // Fallback: log the invalidation request
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for category {$categoryId}"),
                    'cache',
                    'category_update_invalidation'
                );
                return false;
            },
            'category_update_cache_invalidation'
        );
    }

    /**
     * Handle category deletion with error recovery
     */
    public function handleCategoryDelete(int $categoryId): void
    {
        $this->errorHandlingService->executeWithFallback(
            function() use ($categoryId) {
                return $this->cacheService->invalidateCategory($categoryId);
            },
            function() use ($categoryId) {
                // Fallback: log the invalidation request
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for deleted category {$categoryId}"),
                    'cache',
                    'category_delete_invalidation'
                );
                return false;
            },
            'category_delete_cache_invalidation'
        );
    }

    /**
     * Handle stock changes with error recovery
     */
    public function handleStockChange($product): void
    {
        if (!$product instanceof \WC_Product) {
            return;
        }
        
        $productId = $product->get_id();
        
        $this->errorHandlingService->executeWithFallback(
            function() use ($productId, $product) {
                $success = $this->cacheService->invalidateProduct($productId);
                
                // If this is a variation, also invalidate parent
                if ($product->is_type('variation')) {
                    $parentId = $product->get_parent_id();
                    if ($parentId) {
                        $success = $this->cacheService->invalidateProduct($parentId) && $success;
                    }
                }
                
                return $success;
            },
            function() use ($productId) {
                // Fallback: log the invalidation request
                $this->loggingService->logError(
                    new \Exception("Cache invalidation failed for stock change on product {$productId}"),
                    'cache',
                    'stock_change_invalidation'
                );
                return false;
            },
            'stock_change_cache_invalidation'
        );
    }

    /**
     * Handle bulk variation updates with error recovery
     */
    public function handleBulkVariationUpdate(string $bulkAction, array $data, int $productId, array $variations): void
    {
        $this->errorHandlingService->executeWithFallback(
            function() use ($productId, $variations) {
                // Invalidate parent product
                $success = $this->cacheService->invalidateProduct($productId);
                
                // Invalidate all affected variations
                $variationIds = array_keys($variations);
                if (!empty($variationIds)) {
                    $success = $this->cacheService->invalidateBulkProducts($variationIds) && $success;
                }
                
                return $success;
            },
            function() use ($productId, $variations) {
                // Fallback: log the invalidation request
                $variationCount = count($variations);
                $this->loggingService->logError(
                    new \Exception("Bulk cache invalidation failed for product {$productId} and {$variationCount} variations"),
                    'cache',
                    'bulk_variation_invalidation'
                );
                return false;
            },
            'bulk_variation_cache_invalidation'
        );
    }

    /**
     * Manual cache invalidation with error handling
     */
    public function invalidateProductManually(int $productId): array
    {
        return $this->errorHandlingService->executeWithFallback(
            function() use ($productId) {
                $success = $this->cacheService->invalidateProduct($productId);
                if (!$success) {
                    throw new \Exception("Failed to invalidate cache for product {$productId}");
                }
                return ['success' => true, 'product_id' => $productId];
            },
            function() use ($productId) {
                return ['success' => false, 'product_id' => $productId, 'error' => 'Cache invalidation failed'];
            },
            'manual_product_invalidation'
        );
    }

    /**
     * Manual bulk cache invalidation with error handling
     */
    public function invalidateBulkProductsManually(array $productIds): array
    {
        return $this->errorHandlingService->executeWithFallback(
            function() use ($productIds) {
                // Check memory usage before bulk operation
                if (!$this->errorHandlingService->checkMemoryUsage()) {
                    throw new \Exception('Insufficient memory for bulk cache invalidation');
                }
                
                $success = $this->cacheService->invalidateBulkProducts($productIds);
                if (!$success) {
                    throw new \Exception("Failed to invalidate cache for " . count($productIds) . " products");
                }
                
                return [
                    'success' => true, 
                    'product_count' => count($productIds),
                    'product_ids' => $productIds
                ];
            },
            function() use ($productIds) {
                return [
                    'success' => false, 
                    'product_count' => count($productIds),
                    'product_ids' => $productIds,
                    'error' => 'Bulk cache invalidation failed'
                ];
            },
            'manual_bulk_invalidation'
        );
    }

    /**
     * Flush all cache with error handling
     */
    public function flushAllCache(): array
    {
        return $this->errorHandlingService->executeWithFallback(
            function() {
                $success = $this->cacheService->flush();
                if (!$success) {
                    throw new \Exception("Failed to flush all cache");
                }
                return ['success' => true, 'message' => 'All cache flushed successfully'];
            },
            function() {
                return ['success' => false, 'error' => 'Cache flush failed'];
            },
            'flush_all_cache'
        );
    }

    /**
     * Get cache invalidation statistics
     */
    public function getInvalidationStats(): array
    {
        try {
            $stats = $this->cacheService->getStats();
            
            // Add invalidation-specific statistics
            $stats['invalidation_events'] = [
                'product_updates' => get_option('forooshyar_invalidation_product_updates', 0),
                'product_deletes' => get_option('forooshyar_invalidation_product_deletes', 0),
                'variation_updates' => get_option('forooshyar_invalidation_variation_updates', 0),
                'category_updates' => get_option('forooshyar_invalidation_category_updates', 0),
                'stock_changes' => get_option('forooshyar_invalidation_stock_changes', 0),
                'bulk_operations' => get_option('forooshyar_invalidation_bulk_operations', 0)
            ];
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->loggingService->logError($e, 'cache', 'get_invalidation_stats');
            
            return [
                'enabled' => false,
                'total_entries' => 0,
                'invalidated_keys' => 0,
                'invalidation_events' => [],
                'error' => 'Failed to get invalidation statistics'
            ];
        }
    }

    /**
     * Process failed invalidation queue (for recovery)
     */
    public function processFailedInvalidations(): array
    {
        try {
            // Get failed invalidation requests from logs
            $failedInvalidations = $this->getFailedInvalidationRequests();
            
            $processed = 0;
            $failed = 0;
            
            foreach ($failedInvalidations as $invalidation) {
                try {
                    switch ($invalidation['type']) {
                        case 'product':
                            $this->cacheService->invalidateProduct($invalidation['id']);
                            break;
                        case 'category':
                            $this->cacheService->invalidateCategory($invalidation['id']);
                            break;
                        case 'bulk':
                            $this->cacheService->invalidateBulkProducts($invalidation['ids']);
                            break;
                    }
                    $processed++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->loggingService->logError($e, 'cache', 'retry_failed_invalidation');
                }
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($failedInvalidations)
            ];
            
        } catch (\Exception $e) {
            $this->loggingService->logError($e, 'cache', 'process_failed_invalidations');
            
            return [
                'success' => false,
                'error' => 'Failed to process failed invalidations'
            ];
        }
    }

    /**
     * Get failed invalidation requests from error logs
     */
    private function getFailedInvalidationRequests(): array
    {
        // This would parse error logs to find failed cache invalidation requests
        // For now, return empty array as this is a complex implementation
        return [];
    }
}