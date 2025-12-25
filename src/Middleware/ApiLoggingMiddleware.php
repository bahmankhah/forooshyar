<?php

namespace Forooshyar\Middleware;

use Forooshyar\Services\ApiLogService;
use WP_REST_Request;
use WP_REST_Response;
use WPLite\Container;

class ApiLoggingMiddleware
{
    /** @var ApiLogService */
    private $logService;

    public function __construct(ApiLogService $logService = null)
    {
        $this->logService = $logService ?? Container::resolve(ApiLogService::class);
    }

    /**
     * Handle API request with logging and rate limiting
     */
    public function handle(WP_REST_Request $request, callable $next): WP_REST_Response
    {
        $startTime = microtime(true);
        $endpoint = method_exists($request, 'get_route') ? $request->get_route() : $request->get_param('route') ?? '/unknown';
        
        // Check rate limiting first
        appLogger('LOGGING ' . $endpoint);
        $rateLimitCheck = $this->logService->checkRateLimit($endpoint);
        
        if (!$rateLimitCheck['allowed']) {
            $response = new WP_REST_Response([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'محدودیت تعداد درخواست‌ها',
                    'details' => sprintf(
                        'حداکثر %d درخواست در ساعت مجاز است. لطفاً %d ثانیه دیگر تلاش کنید.',
                        $rateLimitCheck['limit'],
                        $rateLimitCheck['reset_time'] - time()
                    )
                ],
                'data' => [
                    'rate_limit' => [
                        'limit' => $rateLimitCheck['limit'],
                        'remaining' => $rateLimitCheck['remaining'],
                        'reset_time' => $rateLimitCheck['reset_time']
                    ]
                ]
            ], 429);
            
            // Log the rate limit violation
            $this->logRequest($request, $response, microtime(true) - $startTime, false);
            
            return $response;
        }
        
        // Execute the actual request
        $response = $next($request);
        
        // Calculate response time
        $responseTime = microtime(true) - $startTime;
        
        // Determine if this was a cache hit (check if response has cache headers or metadata)
        $cacheHit = $this->detectCacheHit($response);
        
        // Log the request
        $this->logRequest($request, $response, $responseTime, $cacheHit);
        
        // Add rate limit headers to response
        $this->addRateLimitHeaders($response, $rateLimitCheck);
        
        return $response;
    }

    /**
     * Log the API request with all relevant details
     */
    private function logRequest(WP_REST_Request $request, WP_REST_Response $response, float $responseTime, bool $cacheHit): void
    {
        $responseData = $response->get_data();
        $responseSize = strlen(json_encode($responseData));
        $statusCode = $response->get_status();
        
        // Extract error message if present
        $errorMessage = null;
        if ($statusCode >= 400 && is_array($responseData) && isset($responseData['error'])) {
            $errorMessage = is_array($responseData['error']) 
                ? ($responseData['error']['message'] ?? 'Unknown error')
                : $responseData['error'];
        }
        
        // Prepare request parameters (sanitize sensitive data)
        $parameters = $this->sanitizeParameters($request->get_params());
        
        $logData = [
            'endpoint' => $request->get_route(),
            'method' => $request->get_method(),
            'parameters' => $parameters,
            'response_time' => $responseTime,
            'response_size' => $responseSize,
            'status_code' => $statusCode,
            'cache_hit' => $cacheHit,
            'error_message' => $errorMessage
        ];
        
        $this->logService->logRequest($logData);
    }

    /**
     * Detect if the response was served from cache
     */
    private function detectCacheHit(WP_REST_Response $response): bool
    {
        // Check global cache hit indicator set by CacheService
        // This is the primary method for detecting cache hits
        if (isset($GLOBALS['forooshyar_cache_hit'])) {
            $cacheHit = (bool) $GLOBALS['forooshyar_cache_hit'];
            // Don't unset - let the caller (admin test) also read it
            // The global will be reset on the next CacheService::get() call
            return $cacheHit;
        }
        
        // Check response headers for cache indicators
        $headers = $response->get_headers();
        
        // Look for custom cache headers that might be set by caching layer
        if (isset($headers['X-Cache-Status']) && $headers['X-Cache-Status'] === 'HIT') {
            return true;
        }
        
        // Check if response data contains cache metadata
        $data = $response->get_data();
        if (is_array($data) && isset($data['_cache_hit'])) {
            return (bool) $data['_cache_hit'];
        }
        
        return false;
    }

    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(WP_REST_Response $response, array $rateLimitInfo): void
    {
        $response->header('X-RateLimit-Limit', $rateLimitInfo['limit']);
        $response->header('X-RateLimit-Remaining', $rateLimitInfo['remaining']);
        $response->header('X-RateLimit-Reset', $rateLimitInfo['reset_time']);
    }

    /**
     * Sanitize request parameters to remove sensitive data
     */
    private function sanitizeParameters(array $parameters): array
    {
        $sanitized = [];
        
        // List of sensitive parameter names to exclude or mask
        $sensitiveKeys = [
            'password',
            'token',
            'api_key',
            'secret',
            'auth',
            'authorization'
        ];
        
        foreach ($parameters as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Skip sensitive parameters
            if (in_array($lowerKey, $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }
            
            // Limit array/object size to prevent huge logs
            if (is_array($value) && count($value) > 100) {
                $sanitized[$key] = '[LARGE_ARRAY_' . count($value) . '_ITEMS]';
                continue;
            }
            
            // Limit string length
            if (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000) . '[TRUNCATED]';
                continue;
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }

    /**
     * Create middleware instance with dependencies
     */
    public static function create(): self
    {
        $configService = new \Forooshyar\Services\ConfigService();
        $logService = new ApiLogService($configService);
        
        return new self($logService);
    }
}