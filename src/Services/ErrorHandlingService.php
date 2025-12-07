<?php

namespace Forooshyar\Services;

use Exception;
use Throwable;

/**
 * Comprehensive error handling and recovery service
 * Implements graceful fallbacks, circuit breaker patterns, and Persian error messages
 */
class ErrorHandlingService
{
    private ConfigService $configService;
    private CacheService $cacheService;
    private LoggingService $loggingService;
    
    // Circuit breaker states
    private const CIRCUIT_CLOSED = 'closed';
    private const CIRCUIT_OPEN = 'open';
    private const CIRCUIT_HALF_OPEN = 'half_open';
    
    // Error categories
    private const ERROR_DATABASE = 'database';
    private const ERROR_CACHE = 'cache';
    private const ERROR_MEMORY = 'memory';
    private const ERROR_TIMEOUT = 'timeout';
    private const ERROR_WOOCOMMERCE = 'woocommerce';
    private const ERROR_VALIDATION = 'validation';
    private const ERROR_CONFIGURATION = 'configuration';
    
    // Circuit breaker configuration
    private const FAILURE_THRESHOLD = 5;
    private const RECOVERY_TIMEOUT = 60; // seconds
    private const HALF_OPEN_MAX_CALLS = 3;
    
    public function __construct(
        ConfigService $configService,
        CacheService $cacheService,
        LoggingService $loggingService
    ) {
        $this->configService = $configService;
        $this->cacheService = $cacheService;
        $this->loggingService = $loggingService;
    }

    /**
     * Execute operation with comprehensive error handling and fallback
     */
    public function executeWithFallback(callable $operation, callable $fallback = null, string $operationName = 'unknown'): array
    {
        try {
            // Check circuit breaker state
            if ($this->isCircuitOpen($operationName)) {
                return $this->handleCircuitOpen($operationName, $fallback);
            }
            
            // Execute the operation
            $result = $this->executeWithCircuitBreaker($operation, $operationName);
            
            // Record success
            $this->recordSuccess($operationName);
            
            return [
                'success' => true,
                'data' => $result,
                'source' => 'primary',
                'error' => null
            ];
            
        } catch (Throwable $e) {
            // Categorize and handle the error
            $errorCategory = $this->categorizeError($e);
            $this->recordFailure($operationName, $errorCategory);
            
            // Log the error with Persian description
            $this->loggingService->logError($e, $errorCategory, $operationName);
            
            // Attempt fallback strategies
            return $this->attemptFallback($e, $fallback, $operationName, $errorCategory);
        }
    }

    /**
     * Execute database operation with cache fallback
     */
    public function executeDatabaseWithCacheFallback(callable $databaseOperation, string $cacheKey, string $operationName = 'database_operation'): array
    {
        $fallback = function() use ($cacheKey) {
            // Try to get cached data as fallback
            $cachedData = $this->cacheService->get($cacheKey . '_fallback');
            if ($cachedData !== false) {
                return $cachedData;
            }
            
            // Return empty result if no cache available
            return [
                'count' => 0,
                'max_pages' => 0,
                'products' => []
            ];
        };
        
        return $this->executeWithFallback($databaseOperation, $fallback, $operationName);
    }

    /**
     * Execute cache operation with database fallback
     */
    public function executeCacheWithDatabaseFallback(callable $cacheOperation, callable $databaseOperation, string $operationName = 'cache_operation'): array
    {
        try {
            $result = $cacheOperation();
            if ($result !== false) {
                return [
                    'success' => true,
                    'data' => $result,
                    'source' => 'cache',
                    'error' => null
                ];
            }
        } catch (Throwable $e) {
            $this->loggingService->logError($e, self::ERROR_CACHE, $operationName);
        }
        
        // Cache failed or returned false, try database
        return $this->executeWithFallback($databaseOperation, null, $operationName . '_database_fallback');
    }

    /**
     * Get error response in Persian
     */
    public function getErrorResponse(Throwable $e, string $operationName = 'unknown'): array
    {
        $errorCategory = $this->categorizeError($e);
        $errorCode = $this->getErrorCode($errorCategory, $e);
        $persianMessage = $this->getPersianErrorMessage($errorCategory, $e);
        
        return [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $persianMessage['message'],
                'details' => $persianMessage['details'],
                'category' => $errorCategory,
                'operation' => $operationName,
                'timestamp' => current_time('mysql')
            ],
            'data' => null
        ];
    }

    /**
     * Check if memory usage is approaching limits
     */
    public function checkMemoryUsage(): bool
    {
        $memoryLimit = $this->getMemoryLimitInBytes();
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        
        // Trigger circuit breaker if using more than 80% of memory limit
        $threshold = $memoryLimit * 0.8;
        
        if ($currentUsage > $threshold || $peakUsage > $threshold) {
            $this->recordFailure('memory_check', self::ERROR_MEMORY);
            return false;
        }
        
        return true;
    }

    /**
     * Execute with timeout protection
     */
    public function executeWithTimeout(callable $operation, int $timeoutSeconds = 30, string $operationName = 'timed_operation')
    {
        $startTime = microtime(true);
        
        try {
            // Set time limit for this operation
            $originalTimeLimit = ini_get('max_execution_time');
            set_time_limit($timeoutSeconds);
            
            $result = $operation();
            
            // Restore original time limit
            set_time_limit($originalTimeLimit);
            
            $executionTime = microtime(true) - $startTime;
            
            // Log slow operations
            if ($executionTime > ($timeoutSeconds * 0.8)) {
                $this->loggingService->logSlowOperation($operationName, $executionTime);
            }
            
            return $result;
            
        } catch (Throwable $e) {
            // Restore original time limit
            set_time_limit($originalTimeLimit);
            
            $executionTime = microtime(true) - $startTime;
            
            // Check if this was a timeout
            if ($executionTime >= $timeoutSeconds) {
                $this->recordFailure($operationName, self::ERROR_TIMEOUT);
                throw new Exception("عملیات به دلیل طولانی شدن متوقف شد (زمان: {$executionTime} ثانیه)", 0, $e);
            }
            
            throw $e;
        }
    }

    /**
     * Categorize error type
     */
    private function categorizeError(Throwable $e): string
    {
        $message = strtolower($e->getMessage());
        
        // Database errors
        if (strpos($message, 'database') !== false || 
            strpos($message, 'mysql') !== false || 
            strpos($message, 'connection') !== false ||
            strpos($message, 'wpdb') !== false) {
            return self::ERROR_DATABASE;
        }
        
        // Cache errors
        if (strpos($message, 'cache') !== false || 
            strpos($message, 'transient') !== false) {
            return self::ERROR_CACHE;
        }
        
        // Memory errors
        if (strpos($message, 'memory') !== false || 
            strpos($message, 'allowed memory size') !== false) {
            return self::ERROR_MEMORY;
        }
        
        // Timeout errors
        if (strpos($message, 'timeout') !== false || 
            strpos($message, 'time limit') !== false ||
            strpos($message, 'execution time') !== false) {
            return self::ERROR_TIMEOUT;
        }
        
        // WooCommerce errors
        if (strpos($message, 'woocommerce') !== false || 
            strpos($message, 'product') !== false ||
            strpos($message, 'wc_') !== false) {
            return self::ERROR_WOOCOMMERCE;
        }
        
        // Validation errors
        if ($e instanceof \InvalidArgumentException) {
            return self::ERROR_VALIDATION;
        }
        
        // Configuration errors
        if (strpos($message, 'config') !== false || 
            strpos($message, 'setting') !== false) {
            return self::ERROR_CONFIGURATION;
        }
        
        // Default to database for unknown errors
        return self::ERROR_DATABASE;
    }

    /**
     * Get error code based on category and exception
     */
    private function getErrorCode(string $category, Throwable $e): string
    {
        $baseCode = strtoupper($category) . '_ERROR';
        
        switch ($category) {
            case self::ERROR_DATABASE:
                return 'DATABASE_CONNECTION_FAILED';
            case self::ERROR_CACHE:
                return 'CACHE_OPERATION_FAILED';
            case self::ERROR_MEMORY:
                return 'MEMORY_LIMIT_EXCEEDED';
            case self::ERROR_TIMEOUT:
                return 'OPERATION_TIMEOUT';
            case self::ERROR_WOOCOMMERCE:
                return 'WOOCOMMERCE_ERROR';
            case self::ERROR_VALIDATION:
                return 'INVALID_INPUT';
            case self::ERROR_CONFIGURATION:
                return 'CONFIGURATION_ERROR';
            default:
                return 'INTERNAL_ERROR';
        }
    }

    /**
     * Get Persian error messages
     */
    private function getPersianErrorMessage(string $category, Throwable $e): array
    {
        switch ($category) {
            case self::ERROR_DATABASE:
                return [
                    'message' => 'خطا در اتصال به پایگاه داده',
                    'details' => 'مشکلی در دسترسی به اطلاعات محصولات وجود دارد. از اطلاعات ذخیره شده استفاده می‌شود.'
                ];
                
            case self::ERROR_CACHE:
                return [
                    'message' => 'خطا در سیستم کش',
                    'details' => 'سیستم ذخیره‌سازی موقت با مشکل مواجه شده است. اطلاعات مستقیماً از پایگاه داده دریافت می‌شود.'
                ];
                
            case self::ERROR_MEMORY:
                return [
                    'message' => 'کمبود حافظه سیستم',
                    'details' => 'حافظه سیستم کافی نیست. لطفاً درخواست کوچکتری ارسال کنید یا بعداً تلاش کنید.'
                ];
                
            case self::ERROR_TIMEOUT:
                return [
                    'message' => 'زمان انتظار تمام شد',
                    'details' => 'عملیات بیش از حد طولانی شد و متوقف گردید. لطفاً بعداً تلاش کنید.'
                ];
                
            case self::ERROR_WOOCOMMERCE:
                return [
                    'message' => 'خطا در سیستم فروشگاه',
                    'details' => 'مشکلی در دسترسی به اطلاعات محصولات فروشگاه وجود دارد. لطفاً بعداً تلاش کنید.'
                ];
                
            case self::ERROR_VALIDATION:
                return [
                    'message' => 'اطلاعات ورودی نامعتبر',
                    'details' => $e->getMessage() ?: 'پارامترهای ارسالی صحیح نیستند. لطفاً اطلاعات را بررسی کنید.'
                ];
                
            case self::ERROR_CONFIGURATION:
                return [
                    'message' => 'خطا در تنظیمات',
                    'details' => 'مشکلی در تنظیمات افزونه وجود دارد. لطفاً با مدیر سایت تماس بگیرید.'
                ];
                
            default:
                return [
                    'message' => 'خطای داخلی سرور',
                    'details' => 'مشکل غیرمنتظره‌ای رخ داده است. لطفاً بعداً تلاش کنید.'
                ];
        }
    }

    /**
     * Attempt fallback strategies
     */
    private function attemptFallback(Throwable $e, ?callable $fallback, string $operationName, string $errorCategory): array
    {
        // Try custom fallback first
        if ($fallback) {
            try {
                $fallbackResult = $fallback();
                return [
                    'success' => true,
                    'data' => $fallbackResult,
                    'source' => 'fallback',
                    'error' => $this->getErrorResponse($e, $operationName)['error']
                ];
            } catch (Throwable $fallbackError) {
                $this->loggingService->logError($fallbackError, 'fallback', $operationName);
            }
        }
        
        // Try category-specific fallbacks
        switch ($errorCategory) {
            case self::ERROR_DATABASE:
                return $this->handleDatabaseError($e, $operationName);
                
            case self::ERROR_CACHE:
                return $this->handleCacheError($e, $operationName);
                
            case self::ERROR_MEMORY:
                return $this->handleMemoryError($e, $operationName);
                
            case self::ERROR_TIMEOUT:
                return $this->handleTimeoutError($e, $operationName);
                
            default:
                return $this->getErrorResponse($e, $operationName);
        }
    }

    /**
     * Handle database errors with cache fallback
     */
    private function handleDatabaseError(Throwable $e, string $operationName): array
    {
        // Try to get cached data as fallback
        $cacheKey = "fallback_{$operationName}";
        $cachedData = $this->cacheService->get($cacheKey);
        
        if ($cachedData !== false) {
            return [
                'success' => true,
                'data' => $cachedData,
                'source' => 'cache_fallback',
                'error' => $this->getErrorResponse($e, $operationName)['error']
            ];
        }
        
        // Return empty result with error
        $errorResponse = $this->getErrorResponse($e, $operationName);
        $errorResponse['data'] = [
            'count' => 0,
            'max_pages' => 0,
            'products' => []
        ];
        
        return $errorResponse;
    }

    /**
     * Handle cache errors
     */
    private function handleCacheError(Throwable $e, string $operationName): array
    {
        // Cache errors are usually not critical, continue without cache
        return $this->getErrorResponse($e, $operationName);
    }

    /**
     * Handle memory errors
     */
    private function handleMemoryError(Throwable $e, string $operationName): array
    {
        // Clear some memory and return minimal response
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        $errorResponse = $this->getErrorResponse($e, $operationName);
        $errorResponse['data'] = [
            'count' => 0,
            'max_pages' => 0,
            'products' => []
        ];
        
        return $errorResponse;
    }

    /**
     * Handle timeout errors
     */
    private function handleTimeoutError(Throwable $e, string $operationName): array
    {
        // Try to get partial cached results
        $cacheKey = "partial_{$operationName}";
        $partialData = $this->cacheService->get($cacheKey);
        
        if ($partialData !== false) {
            return [
                'success' => true,
                'data' => $partialData,
                'source' => 'partial_cache',
                'error' => $this->getErrorResponse($e, $operationName)['error']
            ];
        }
        
        return $this->getErrorResponse($e, $operationName);
    }

    /**
     * Circuit breaker implementation
     */
    private function executeWithCircuitBreaker(callable $operation, string $operationName)
    {
        $circuitState = $this->getCircuitState($operationName);
        
        switch ($circuitState) {
            case self::CIRCUIT_OPEN:
                throw new Exception("Circuit breaker is open for operation: {$operationName}");
                
            case self::CIRCUIT_HALF_OPEN:
                // Allow limited calls in half-open state
                if ($this->getHalfOpenCallCount($operationName) >= self::HALF_OPEN_MAX_CALLS) {
                    throw new Exception("Circuit breaker half-open call limit exceeded for: {$operationName}");
                }
                $this->incrementHalfOpenCallCount($operationName);
                break;
        }
        
        return $operation();
    }

    /**
     * Check if circuit is open
     */
    private function isCircuitOpen(string $operationName): bool
    {
        $circuitState = $this->getCircuitState($operationName);
        
        if ($circuitState === self::CIRCUIT_OPEN) {
            // Check if recovery timeout has passed
            $lastFailureTime = $this->getLastFailureTime($operationName);
            if (time() - $lastFailureTime > self::RECOVERY_TIMEOUT) {
                $this->setCircuitState($operationName, self::CIRCUIT_HALF_OPEN);
                return false;
            }
            return true;
        }
        
        return false;
    }

    /**
     * Handle circuit open state
     */
    private function handleCircuitOpen(string $operationName, ?callable $fallback): array
    {
        if ($fallback) {
            try {
                $fallbackResult = $fallback();
                return [
                    'success' => true,
                    'data' => $fallbackResult,
                    'source' => 'circuit_breaker_fallback',
                    'error' => [
                        'code' => 'CIRCUIT_BREAKER_OPEN',
                        'message' => 'سیستم در حالت محافظت قرار دارد',
                        'details' => 'به دلیل خطاهای مکرر، سیستم موقتاً از حالت عادی خارج شده است'
                    ]
                ];
            } catch (Throwable $e) {
                // Fallback also failed
            }
        }
        
        return [
            'success' => false,
            'error' => [
                'code' => 'CIRCUIT_BREAKER_OPEN',
                'message' => 'سیستم در حالت محافظت قرار دارد',
                'details' => 'به دلیل خطاهای مکرر، سیستم موقتاً غیرفعال شده است. لطفاً بعداً تلاش کنید.'
            ],
            'data' => null
        ];
    }

    /**
     * Record operation success
     */
    private function recordSuccess(string $operationName): void
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key) ?: [
            'failures' => 0,
            'state' => self::CIRCUIT_CLOSED,
            'last_failure_time' => 0,
            'half_open_calls' => 0
        ];
        
        // Reset failure count on success
        $data['failures'] = 0;
        $data['state'] = self::CIRCUIT_CLOSED;
        $data['half_open_calls'] = 0;
        
        set_transient($key, $data, 3600);
    }

    /**
     * Record operation failure
     */
    private function recordFailure(string $operationName, string $errorCategory): void
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key) ?: [
            'failures' => 0,
            'state' => self::CIRCUIT_CLOSED,
            'last_failure_time' => 0,
            'half_open_calls' => 0
        ];
        
        $data['failures']++;
        $data['last_failure_time'] = time();
        
        // Open circuit if failure threshold exceeded
        if ($data['failures'] >= self::FAILURE_THRESHOLD) {
            $data['state'] = self::CIRCUIT_OPEN;
        }
        
        set_transient($key, $data, 3600);
        
        // Log the failure
        $this->loggingService->logCircuitBreakerEvent($operationName, $data['state'], $data['failures']);
    }

    /**
     * Get circuit state
     */
    private function getCircuitState(string $operationName): string
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key);
        
        return $data['state'] ?? self::CIRCUIT_CLOSED;
    }

    /**
     * Set circuit state
     */
    private function setCircuitState(string $operationName, string $state): void
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key) ?: [
            'failures' => 0,
            'state' => self::CIRCUIT_CLOSED,
            'last_failure_time' => 0,
            'half_open_calls' => 0
        ];
        
        $data['state'] = $state;
        if ($state === self::CIRCUIT_HALF_OPEN) {
            $data['half_open_calls'] = 0;
        }
        
        set_transient($key, $data, 3600);
    }

    /**
     * Get last failure time
     */
    private function getLastFailureTime(string $operationName): int
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key);
        
        return $data['last_failure_time'] ?? 0;
    }

    /**
     * Get half-open call count
     */
    private function getHalfOpenCallCount(string $operationName): int
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key);
        
        return $data['half_open_calls'] ?? 0;
    }

    /**
     * Increment half-open call count
     */
    private function incrementHalfOpenCallCount(string $operationName): void
    {
        $key = "circuit_breaker_{$operationName}";
        $data = get_transient($key) ?: [
            'failures' => 0,
            'state' => self::CIRCUIT_CLOSED,
            'last_failure_time' => 0,
            'half_open_calls' => 0
        ];
        
        $data['half_open_calls']++;
        set_transient($key, $data, 3600);
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimitInBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit == -1) {
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
                return $value;
        }
    }

    /**
     * Get circuit breaker statistics
     */
    public function getCircuitBreakerStats(): array
    {
        global $wpdb;
        
        // Get all circuit breaker data
        $results = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_circuit_breaker_%'",
            ARRAY_A
        );
        
        $stats = [];
        foreach ($results as $result) {
            $operationName = str_replace('_transient_circuit_breaker_', '', $result['option_name']);
            $data = maybe_unserialize($result['option_value']);
            
            $stats[$operationName] = [
                'state' => $data['state'] ?? self::CIRCUIT_CLOSED,
                'failures' => $data['failures'] ?? 0,
                'last_failure_time' => $data['last_failure_time'] ?? 0,
                'half_open_calls' => $data['half_open_calls'] ?? 0
            ];
        }
        
        return $stats;
    }
}