<?php

namespace Forooshyar\Services;

use Throwable;

/**
 * Comprehensive logging service for error categorization and monitoring
 */
class LoggingService
{
    private const LOG_TABLE = 'forooshyar_error_logs';
    private const MAX_LOG_ENTRIES = 10000;
    private const LOG_RETENTION_DAYS = 30;
    
    /** @var ConfigService */
    private $configService;
    
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
        $this->ensureLogTableExists();
    }

    /**
     * Log error with Persian categorization
     */
    public function logError(Throwable $e, string $category, string $operation = 'unknown'): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available (e.g., in test environment)
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $errorData = [
            'timestamp' => current_time('mysql'),
            'category' => $category,
            'operation' => $operation,
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'stack_trace' => $this->sanitizeStackTrace($e->getTraceAsString()),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->getClientIpAddress(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'persian_message' => $this->getPersianErrorDescription($category, $e)
        ];
        
        // Insert into custom log table
        $wpdb->insert(
            $wpdb->prefix . self::LOG_TABLE,
            $errorData,
            [
                '%s', // timestamp
                '%s', // category
                '%s', // operation
                '%d', // error_code
                '%s', // error_message
                '%s', // error_file
                '%d', // error_line
                '%s', // stack_trace
                '%s', // user_agent
                '%s', // ip_address
                '%s', // request_uri
                '%s', // request_method
                '%d', // memory_usage
                '%d', // peak_memory
                '%s'  // persian_message
            ]
        );
        
        // Also log to WordPress error log if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Forooshyar] %s در %s: %s (فایل: %s:%d)',
                $category,
                $operation,
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            ));
        }
        
        // Clean up old logs periodically
        if (rand(1, 100) === 1) { // 1% chance
            $this->cleanupOldLogs();
        }
    }

    /**
     * Log performance metrics
     */
    public function logPerformance(string $operation, array $data): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available (e.g., in test environment)
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $performanceData = [
            'timestamp' => current_time('mysql'),
            'category' => 'performance',
            'operation' => $operation,
            'error_code' => 0,
            'error_message' => json_encode($data),
            'error_file' => '',
            'error_line' => 0,
            'stack_trace' => '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->getClientIpAddress(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'memory_usage' => $data['memory_usage'] ?? memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'persian_message' => "عملکرد {$operation}: " . json_encode($data, JSON_UNESCAPED_UNICODE)
        ];
        
        $wpdb->insert(
            $wpdb->prefix . self::LOG_TABLE,
            $performanceData,
            [
                '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s',
                '%s', '%s', '%s', '%s', '%d', '%d', '%s'
            ]
        );
    }

    /**
     * Log slow operation
     */
    public function logSlowOperation(string $operation, float $executionTime): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available (e.g., in test environment)
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $slowLogData = [
            'timestamp' => current_time('mysql'),
            'category' => 'performance',
            'operation' => $operation,
            'error_code' => 0,
            'error_message' => "عملیات کند: {$executionTime} ثانیه",
            'error_file' => '',
            'error_line' => 0,
            'stack_trace' => '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->getClientIpAddress(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'persian_message' => "عملیات {$operation} در {$executionTime} ثانیه اجرا شد که بیش از حد انتظار است"
        ];
        
        $wpdb->insert(
            $wpdb->prefix . self::LOG_TABLE,
            $slowLogData,
            [
                '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s',
                '%s', '%s', '%s', '%s', '%d', '%d', '%s'
            ]
        );
    }

    /**
     * Log circuit breaker events
     */
    public function logCircuitBreakerEvent(string $operation, string $state, int $failureCount): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available (e.g., in test environment)
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $stateMessages = [
            'open' => 'باز شد',
            'closed' => 'بسته شد',
            'half_open' => 'نیمه باز شد'
        ];
        
        $persianState = $stateMessages[$state] ?? $state;
        
        $circuitLogData = [
            'timestamp' => current_time('mysql'),
            'category' => 'circuit_breaker',
            'operation' => $operation,
            'error_code' => $failureCount,
            'error_message' => "Circuit breaker {$state} for {$operation}",
            'error_file' => '',
            'error_line' => 0,
            'stack_trace' => '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->getClientIpAddress(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'persian_message' => "محافظ مدار برای عملیات {$operation} {$persianState} (تعداد خطا: {$failureCount})"
        ];
        
        $wpdb->insert(
            $wpdb->prefix . self::LOG_TABLE,
            $circuitLogData,
            [
                '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s',
                '%s', '%s', '%s', '%s', '%d', '%d', '%s'
            ]
        );
    }

    /**
     * Get error logs with filtering and pagination
     */
    public function getErrorLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        global $wpdb;
        
        // Return empty array if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'logs' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
        
        $where = ['1=1'];
        $whereValues = [];
        
        // Apply filters
        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $whereValues[] = $filters['category'];
        }
        
        if (!empty($filters['operation'])) {
            $where[] = 'operation LIKE %s';
            $whereValues[] = '%' . $filters['operation'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'timestamp >= %s';
            $whereValues[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'timestamp <= %s';
            $whereValues[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE {$whereClause}";
        if (!empty($whereValues)) {
            $countQuery = $wpdb->prepare($countQuery, $whereValues);
        }
        $totalCount = $wpdb->get_var($countQuery);
        
        // Get logs
        $logsQuery = "
            SELECT * FROM {$wpdb->prefix}" . self::LOG_TABLE . " 
            WHERE {$whereClause} 
            ORDER BY timestamp DESC 
            LIMIT %d OFFSET %d
        ";
        
        $queryValues = array_merge($whereValues, [$perPage, $offset]);
        $logs = $wpdb->get_results($wpdb->prepare($logsQuery, $queryValues), ARRAY_A);
        
        return [
            'logs' => $logs,
            'total_count' => (int) $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalCount / $perPage)
        ];
    }

    /**
     * Get error statistics
     */
    public function getErrorStatistics(int $days = 7): array
    {
        global $wpdb;
        
        // Return empty statistics if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return [
                'total_errors' => 0,
                'by_category' => [],
                'by_operation' => [],
                'by_day' => [],
                'most_common_errors' => []
            ];
        }
        
        $dateFrom = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Error count by category
        $categoryStats = $wpdb->get_results($wpdb->prepare("
            SELECT category, COUNT(*) as count
            FROM {$wpdb->prefix}" . self::LOG_TABLE . "
            WHERE timestamp >= %s
            GROUP BY category
            ORDER BY count DESC
        ", $dateFrom), ARRAY_A);
        
        // Error count by operation
        $operationStats = $wpdb->get_results($wpdb->prepare("
            SELECT operation, COUNT(*) as count
            FROM {$wpdb->prefix}" . self::LOG_TABLE . "
            WHERE timestamp >= %s
            GROUP BY operation
            ORDER BY count DESC
            LIMIT 10
        ", $dateFrom), ARRAY_A);
        
        // Daily error counts
        $dailyStats = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(timestamp) as date, COUNT(*) as count
            FROM {$wpdb->prefix}" . self::LOG_TABLE . "
            WHERE timestamp >= %s
            GROUP BY DATE(timestamp)
            ORDER BY date DESC
        ", $dateFrom), ARRAY_A);
        
        // Memory usage statistics
        $memoryStats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(memory_usage) as avg_memory,
                MAX(memory_usage) as max_memory,
                AVG(peak_memory) as avg_peak_memory,
                MAX(peak_memory) as max_peak_memory
            FROM {$wpdb->prefix}" . self::LOG_TABLE . "
            WHERE timestamp >= %s AND memory_usage > 0
        ", $dateFrom), ARRAY_A);
        
        // Top error messages
        $topErrors = $wpdb->get_results($wpdb->prepare("
            SELECT error_message, COUNT(*) as count
            FROM {$wpdb->prefix}" . self::LOG_TABLE . "
            WHERE timestamp >= %s
            GROUP BY error_message
            ORDER BY count DESC
            LIMIT 5
        ", $dateFrom), ARRAY_A);
        
        return [
            'period_days' => $days,
            'category_stats' => $categoryStats,
            'operation_stats' => $operationStats,
            'daily_stats' => $dailyStats,
            'memory_stats' => $memoryStats,
            'top_errors' => $topErrors,
            'total_errors' => array_sum(array_column($categoryStats, 'count'))
        ];
    }

    /**
     * Clear error logs
     */
    public function clearLogs(array $filters = []): int
    {
        global $wpdb;
        
        // Return 0 if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return 0;
        }
        
        $where = ['1=1'];
        $whereValues = [];
        
        // Apply filters
        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $whereValues[] = $filters['category'];
        }
        
        if (!empty($filters['older_than_days'])) {
            $where[] = 'timestamp < %s';
            $whereValues[] = date('Y-m-d H:i:s', strtotime("-{$filters['older_than_days']} days"));
        }
        
        $whereClause = implode(' AND ', $where);
        $deleteQuery = "DELETE FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE {$whereClause}";
        
        if (!empty($whereValues)) {
            $deleteQuery = $wpdb->prepare($deleteQuery, $whereValues);
        }
        
        return $wpdb->query($deleteQuery);
    }

    /**
     * Export error logs
     */
    public function exportLogs(array $filters = []): string
    {
        $logs = $this->getErrorLogs($filters, 1, 10000); // Get up to 10k logs
        
        $csv = "تاریخ,دسته‌بندی,عملیات,کد خطا,پیام خطا,فایل,خط,آدرس IP,پیام فارسی\n";
        
        foreach ($logs['logs'] as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%d,\"%s\",\"%s\",%d,%s,\"%s\"\n",
                $log['timestamp'],
                $log['category'],
                $log['operation'],
                $log['error_code'],
                str_replace('"', '""', $log['error_message']),
                basename($log['error_file']),
                $log['error_line'],
                $log['ip_address'],
                str_replace('"', '""', $log['persian_message'])
            );
        }
        
        return $csv;
    }

    /**
     * Get Persian error description
     */
    private function getPersianErrorDescription(string $category, Throwable $e): string
    {
        $descriptions = [
            'database' => 'خطا در پایگاه داده: ' . $e->getMessage(),
            'cache' => 'خطا در سیستم کش: ' . $e->getMessage(),
            'memory' => 'خطا در حافظه سیستم: ' . $e->getMessage(),
            'timeout' => 'خطا در زمان انتظار: ' . $e->getMessage(),
            'woocommerce' => 'خطا در سیستم فروشگاه: ' . $e->getMessage(),
            'validation' => 'خطا در اعتبارسنجی: ' . $e->getMessage(),
            'configuration' => 'خطا در تنظیمات: ' . $e->getMessage(),
            'circuit_breaker' => 'فعال‌سازی محافظ مدار: ' . $e->getMessage(),
            'performance' => 'مشکل عملکرد: ' . $e->getMessage()
        ];
        
        return $descriptions[$category] ?? 'خطای نامشخص: ' . $e->getMessage();
    }

    /**
     * Sanitize stack trace for logging
     */
    private function sanitizeStackTrace(string $stackTrace): string
    {
        // Remove sensitive information from stack trace
        $stackTrace = preg_replace('/\/[^\/\s]+\/wp-config\.php/', '/****/wp-config.php', $stackTrace);
        $stackTrace = preg_replace('/\/[^\/\s]+\/wp-content\//', '/****/wp-content/', $stackTrace);
        
        // Limit stack trace length
        if (strlen($stackTrace) > 5000) {
            $stackTrace = substr($stackTrace, 0, 5000) . '... (truncated)';
        }
        
        return $stackTrace;
    }

    /**
     * Get client IP address
     */
    private function getClientIpAddress(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Ensure log table exists
     */
    private function ensureLogTableExists(): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available (e.g., in test environment)
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        // Check if table exists
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $tableName
        ));
        
        if ($tableExists !== $tableName) {
            $this->createLogTable();
        }
    }

    /**
     * Create log table
     */
    private function createLogTable(): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            category varchar(50) NOT NULL,
            operation varchar(100) NOT NULL,
            error_code int(11) NOT NULL DEFAULT 0,
            error_message text NOT NULL,
            error_file varchar(500) NOT NULL DEFAULT '',
            error_line int(11) NOT NULL DEFAULT 0,
            stack_trace longtext NOT NULL DEFAULT '',
            user_agent varchar(500) NOT NULL DEFAULT '',
            ip_address varchar(45) NOT NULL DEFAULT '',
            request_uri varchar(500) NOT NULL DEFAULT '',
            request_method varchar(10) NOT NULL DEFAULT '',
            memory_usage bigint(20) NOT NULL DEFAULT 0,
            peak_memory bigint(20) NOT NULL DEFAULT 0,
            persian_message text NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY category (category),
            KEY operation (operation),
            KEY timestamp (timestamp),
            KEY ip_address (ip_address)
        ) {$charset};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Clean up old logs
     */
    private function cleanupOldLogs(): void
    {
        global $wpdb;
        
        // Skip if wpdb is not available
        if (!isset($wpdb) || !$wpdb) {
            return;
        }
        
        $tableName = $wpdb->prefix . self::LOG_TABLE;
        
        // Delete logs older than retention period
        $retentionDate = date('Y-m-d H:i:s', strtotime('-' . self::LOG_RETENTION_DAYS . ' days'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tableName} WHERE timestamp < %s",
            $retentionDate
        ));
        
        // If still too many logs, delete oldest ones
        $logCount = $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}");
        if ($logCount > self::MAX_LOG_ENTRIES) {
            $deleteCount = $logCount - self::MAX_LOG_ENTRIES;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tableName} ORDER BY timestamp ASC LIMIT %d",
                $deleteCount
            ));
        }
    }
}