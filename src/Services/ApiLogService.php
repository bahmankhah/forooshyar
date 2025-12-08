<?php

namespace Forooshyar\Services;

class ApiLogService
{
    private const LOG_TABLE = 'forooshyar_api_logs';
    private const RATE_LIMIT_TABLE = 'forooshyar_rate_limits';
    private const MAX_LOG_ENTRIES = 10000; // Maximum log entries to keep
    private const CLEANUP_BATCH_SIZE = 1000; // Batch size for cleanup operations
    
    /** @var ConfigService */
    private $configService;

    public function __construct(ConfigService $configService, bool $createTables = true)
    {
        $this->configService = $configService;
        
        if ($createTables) {
            $this->createTables();
        }
    }

    /**
     * Log API request with all relevant details
     */
    public function logRequest(array $requestData): bool
    {
        global $wpdb;
        
        // Skip if wpdb is not available (e.g., in test environment)
        if (!isset($wpdb) || !$wpdb) {
            return true; // Return success to avoid breaking the flow
        }
        
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        $logData = [
            'ip_address' => $this->getClientIp(),
            'endpoint' => $requestData['endpoint'] ?? '',
            'method' => $requestData['method'] ?? 'GET',
            'parameters' => json_encode($requestData['parameters'] ?? []),
            'response_time' => $requestData['response_time'] ?? 0,
            'response_size' => $requestData['response_size'] ?? 0,
            'status_code' => $requestData['status_code'] ?? 200,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'cache_hit' => $requestData['cache_hit'] ?? false,
            'error_message' => $requestData['error_message'] ?? null,
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert(
            $tableName,
            $logData,
            [
                '%s', // ip_address
                '%s', // endpoint
                '%s', // method
                '%s', // parameters
                '%f', // response_time
                '%d', // response_size
                '%d', // status_code
                '%s', // user_agent
                '%s', // referer
                '%d', // cache_hit
                '%s', // error_message
                '%s'  // created_at
            ]
        );
        
        // Trigger cleanup if needed
        $this->maybeCleanupLogs();
        
        return $result !== false;
    }

    /**
     * Check rate limiting for IP address
     */
    public function checkRateLimit(string $endpoint = ''): array
    {
        $ip = $this->getClientIp();
        $config = $this->configService->get('api', []);
        $rateLimit = $config['rate_limit'] ?? 1000; // requests per hour
        $timeWindow = 3600; // 1 hour in seconds
        
        global $wpdb;
        
        // Return allowed if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'allowed' => true,
                'remaining' => $rateLimit,
                'reset_time' => time() + $timeWindow
            ];
        }
        
        $tableName = $wpdb->prefix . self::RATE_LIMIT_TABLE;
        
        // Clean old rate limit entries
        $cutoffTime = time() - $timeWindow;
        $cutoffDate = date('Y-m-d H:i:s', $cutoffTime);
        
        // Validate the date before using it
        if ($cutoffTime > 0 && $cutoffDate !== false && $cutoffDate !== '') {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$tableName} WHERE created_at < %s",
                    $cutoffDate
                )
            );
        }
        
        // Count requests from this IP in the time window
        $requestCount = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} 
                WHERE ip_address = %s 
                AND created_at > %s",
                $ip,
                date('Y-m-d H:i:s', time() - $timeWindow)
            )
        );
        
        $isAllowed = $requestCount < $rateLimit;
        $remainingRequests = max(0, $rateLimit - $requestCount);
        
        if ($isAllowed) {
            // Record this request
            $wpdb->insert(
                $tableName,
                [
                    'ip_address' => $ip,
                    'endpoint' => $endpoint,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s']
            );
        }
        
        return [
            'allowed' => $isAllowed,
            'remaining' => $remainingRequests,
            'limit' => $rateLimit,
            'reset_time' => time() + $timeWindow,
            'current_count' => $requestCount
        ];
    }

    /**
     * Get usage analytics and performance tracking
     */
    public function getAnalytics(array $filters = []): array
    {
        global $wpdb;
        
        // Return empty analytics if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'total_requests' => 0,
                'success_rate' => 0,
                'average_response_time' => 0,
                'requests_by_endpoint' => [],
                'requests_by_hour' => [],
                'error_rates' => []
            ];
        }
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        $whereClause = '1=1';
        $params = [];
        
        // Apply date filters
        if (!empty($filters['start_date'])) {
            $whereClause .= ' AND created_at >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $whereClause .= ' AND created_at <= %s';
            $params[] = $filters['end_date'];
        }
        
        // Apply endpoint filter
        if (!empty($filters['endpoint'])) {
            $whereClause .= ' AND endpoint LIKE %s';
            $params[] = '%' . $filters['endpoint'] . '%';
        }
        
        // Basic statistics
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_requests,
                    AVG(response_time) as avg_response_time,
                    MAX(response_time) as max_response_time,
                    MIN(response_time) as min_response_time,
                    AVG(response_size) as avg_response_size,
                    SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count
                FROM {$tableName} 
                WHERE {$whereClause}",
                $params
            ),
            ARRAY_A
        );
        
        // Top endpoints
        $topEndpoints = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    endpoint,
                    COUNT(*) as request_count,
                    AVG(response_time) as avg_response_time
                FROM {$tableName} 
                WHERE {$whereClause}
                GROUP BY endpoint 
                ORDER BY request_count DESC 
                LIMIT 10",
                $params
            ),
            ARRAY_A
        );
        
        // Top IPs
        $topIps = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    ip_address,
                    COUNT(*) as request_count,
                    AVG(response_time) as avg_response_time
                FROM {$tableName} 
                WHERE {$whereClause}
                GROUP BY ip_address 
                ORDER BY request_count DESC 
                LIMIT 10",
                $params
            ),
            ARRAY_A
        );
        
        // Hourly distribution (last 24 hours)
        $hourlyStats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as request_count,
                    AVG(response_time) as avg_response_time
                FROM {$tableName} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(created_at) 
                ORDER BY hour",
                []
            ),
            ARRAY_A
        );
        
        // Status code distribution
        $statusCodes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    status_code,
                    COUNT(*) as count
                FROM {$tableName} 
                WHERE {$whereClause}
                GROUP BY status_code 
                ORDER BY count DESC",
                $params
            ),
            ARRAY_A
        );
        
        // Calculate cache hit rate
        $cacheHitRate = 0;
        if ($stats['total_requests'] > 0) {
            $cacheHitRate = ($stats['cache_hits'] / $stats['total_requests']) * 100;
        }
        
        return [
            'summary' => [
                'total_requests' => (int) $stats['total_requests'],
                'avg_response_time' => round((float) $stats['avg_response_time'], 3),
                'max_response_time' => round((float) $stats['max_response_time'], 3),
                'min_response_time' => round((float) $stats['min_response_time'], 3),
                'avg_response_size' => (int) $stats['avg_response_size'],
                'cache_hit_rate' => round($cacheHitRate, 2),
                'error_rate' => $stats['total_requests'] > 0 ? 
                    round(($stats['error_count'] / $stats['total_requests']) * 100, 2) : 0
            ],
            'top_endpoints' => $topEndpoints,
            'top_ips' => $topIps,
            'hourly_distribution' => $hourlyStats,
            'status_codes' => $statusCodes
        ];
    }

    /**
     * Get recent logs with pagination
     */
    public function getLogs(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        global $wpdb;
        
        // Return empty logs if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'logs' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        $offset = ($page - 1) * $perPage;
        $whereClause = '1=1';
        $params = [];
        
        // Apply filters
        if (!empty($filters['ip'])) {
            $whereClause .= ' AND ip_address = %s';
            $params[] = $filters['ip'];
        }
        
        if (!empty($filters['endpoint'])) {
            $whereClause .= ' AND endpoint LIKE %s';
            $params[] = '%' . $filters['endpoint'] . '%';
        }
        
        if (!empty($filters['status_code'])) {
            $whereClause .= ' AND status_code = %d';
            $params[] = $filters['status_code'];
        }
        
        if (!empty($filters['start_date'])) {
            $whereClause .= ' AND created_at >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $whereClause .= ' AND created_at <= %s';
            $params[] = $filters['end_date'];
        }
        
        // Get total count
        $totalCount = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE {$whereClause}",
                $params
            )
        );
        
        // Get logs
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tableName} 
                WHERE {$whereClause}
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                array_merge($params, [$perPage, $offset])
            ),
            ARRAY_A
        );
        
        // Decode parameters for display
        foreach ($logs as &$log) {
            $log['parameters'] = json_decode($log['parameters'], true);
        }
        
        return [
            'logs' => $logs,
            'total' => (int) $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalCount / $perPage)
        ];
    }

    /**
     * Clean up old logs to prevent database bloat
     */
    public function cleanupLogs(int $daysToKeep = 30): array
    {
        global $wpdb;
        
        // Return empty result if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'deleted_logs' => 0,
                'deleted_rate_limits' => 0,
                'success' => true
            ];
        }
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        // Count logs to be deleted
        $logsToDelete = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE created_at < %s",
                $cutoffDate
            )
        );
        
        $deletedCount = 0;
        
        // Delete in batches to avoid memory issues
        while (true) {
            $batchDeleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$tableName} WHERE created_at < %s LIMIT %d",
                    $cutoffDate,
                    self::CLEANUP_BATCH_SIZE
                )
            );
            
            if ($batchDeleted === false || $batchDeleted === 0) {
                break;
            }
            
            $deletedCount += $batchDeleted;
            
            // Prevent timeout on large datasets
            if (function_exists('wp_suspend_cache_invalidation')) {
                wp_suspend_cache_invalidation(true);
            }
        }
        
        // Also cleanup rate limit table
        $rateLimitTable = $wpdb->prefix . self::RATE_LIMIT_TABLE;
        $rateLimitCutoffTime = strtotime('-1 day');
        $rateLimitCutoffDate = date('Y-m-d H:i:s', $rateLimitCutoffTime);
        $rateLimitDeleted = 0;
        // Validate the date before using it
        if ($rateLimitCutoffTime !== false && $rateLimitCutoffDate !== false && $rateLimitCutoffDate !== '') {
            $rateLimitDeleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$rateLimitTable} WHERE created_at < %s",
                    $rateLimitCutoffDate
                )
            );
        }
        
        return [
            'logs_deleted' => $deletedCount,
            'rate_limits_deleted' => $rateLimitDeleted ?: 0,
            'cutoff_date' => $cutoffDate
        ];
    }

    /**
     * Get performance metrics for monitoring
     */
    public function getPerformanceMetrics(): array
    {
        global $wpdb;
        
        // Return empty metrics if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'average_response_time' => 0,
                'slowest_endpoints' => [],
                'fastest_endpoints' => [],
                'response_time_distribution' => []
            ];
        }
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        // Get metrics for last 24 hours
        $metrics = $wpdb->get_row(
            "SELECT 
                COUNT(*) as requests_24h,
                AVG(response_time) as avg_response_time_24h,
                MAX(response_time) as max_response_time_24h,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits_24h,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors_24h
            FROM {$tableName} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        );
        
        // Get current rate limit status
        $rateLimitTable = $wpdb->prefix . self::RATE_LIMIT_TABLE;
        $currentRateLimit = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$rateLimitTable} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        // Get database size
        $logTableSize = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName}",
                []
            )
        );
        
        return [
            'requests_last_24h' => (int) $metrics['requests_24h'],
            'avg_response_time_24h' => round((float) $metrics['avg_response_time_24h'], 3),
            'max_response_time_24h' => round((float) $metrics['max_response_time_24h'], 3),
            'cache_hit_rate_24h' => $metrics['requests_24h'] > 0 ? 
                round(($metrics['cache_hits_24h'] / $metrics['requests_24h']) * 100, 2) : 0,
            'error_rate_24h' => $metrics['requests_24h'] > 0 ? 
                round(($metrics['errors_24h'] / $metrics['requests_24h']) * 100, 2) : 0,
            'current_rate_limit_usage' => (int) $currentRateLimit,
            'total_log_entries' => (int) $logTableSize,
            'cleanup_needed' => $logTableSize > self::MAX_LOG_ENTRIES
        ];
    }

    /**
     * Get client IP address with proxy support
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Maybe cleanup logs if threshold is reached
     */
    private function maybeCleanupLogs(): void
    {
        // Only check occasionally to avoid performance impact
        if (rand(1, 100) > 5) { // 5% chance
            return;
        }
        
        global $wpdb;
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        $logCount = $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}");
        
        if ($logCount > self::MAX_LOG_ENTRIES) {
            // Cleanup logs older than 30 days
            $this->cleanupLogs(30);
        }
    }

    /**
     * Create database tables for logging
     */
    private function createTables(): void
    {
        global $wpdb;
        
        // Skip table creation if $wpdb is not available (e.g., in tests)
        if (!$wpdb || !method_exists($wpdb, 'get_charset_collate')) {
            return;
        }
        
        $logTable = $wpdb->prefix . self::LOG_TABLE;
        $rateLimitTable = $wpdb->prefix . self::RATE_LIMIT_TABLE;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // API logs table
        $logTableSql = "CREATE TABLE IF NOT EXISTS {$logTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL DEFAULT 'GET',
            parameters longtext,
            response_time decimal(10,3) NOT NULL DEFAULT 0,
            response_size int(11) NOT NULL DEFAULT 0,
            status_code int(3) NOT NULL DEFAULT 200,
            user_agent text,
            referer text,
            cache_hit tinyint(1) NOT NULL DEFAULT 0,
            error_message text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_ip_address (ip_address),
            KEY idx_endpoint (endpoint),
            KEY idx_created_at (created_at),
            KEY idx_status_code (status_code)
        ) {$charset_collate};";
        
        // Rate limiting table
        $rateLimitTableSql = "CREATE TABLE IF NOT EXISTS {$rateLimitTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            endpoint varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_ip_created (ip_address, created_at),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($logTableSql);
        dbDelta($rateLimitTableSql);
    }
}