<?php

namespace Forooshyar\Services;

class LogCleanupService
{
    private const CLEANUP_HOOK = 'forooshyar_cleanup_logs_cron';
    private const CLEANUP_INTERVAL = 'daily';
    
    private ConfigService $configService;
    private ApiLogService $logService;

    public function __construct(ConfigService $configService, ApiLogService $logService)
    {
        $this->configService = $configService;
        $this->logService = $logService;
    }

    /**
     * Initialize the cleanup service
     */
    public function init(): void
    {
        // Schedule the cleanup task if not already scheduled
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), self::CLEANUP_INTERVAL, self::CLEANUP_HOOK);
        }
        // Hook the cleanup function
        add_action(self::CLEANUP_HOOK, [$this, 'performCleanup']);
        // Hook plugin deactivation to clear scheduled events
        register_deactivation_hook(__FILE__, [$this, 'clearScheduledEvents']);
    }

    /**
     * Perform the actual cleanup
     */
    public function performCleanup(): void
    {
        try {
            // Get retention period from configuration
            $advancedConfig = $this->configService->get('advanced', []);
            $retentionDays = $advancedConfig['log_retention'] ?? 30;
            
            // Ensure retention period is within reasonable bounds
            $retentionDays = max(1, min(365, $retentionDays));
            
            // Perform cleanup
            $result = $this->logService->cleanupLogs($retentionDays);
            
            // Log the cleanup result
            error_log(sprintf(
                'Forooshyar log cleanup completed: %d logs deleted, %d rate limit records deleted',
                $result['logs_deleted'],
                $result['rate_limits_deleted']
            ));
            
            // Update cleanup statistics
            $this->updateCleanupStats($result);
            
        } catch (\Exception $e) {
            error_log('Forooshyar log cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear scheduled events (called on plugin deactivation)
     */
    public function clearScheduledEvents(): void
    {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }
    }

    /**
     * Force cleanup now (for manual cleanup)
     */
    public function forceCleanup(int $retentionDays = null): array
    {
        if ($retentionDays === null) {
            $advancedConfig = $this->configService->get('advanced', []);
            $retentionDays = $advancedConfig['log_retention'] ?? 30;
        }
        
        $retentionDays = max(1, min(365, $retentionDays));
        
        $result = $this->logService->cleanupLogs($retentionDays);
        $this->updateCleanupStats($result);
        
        return $result;
    }

    /**
     * Get cleanup statistics
     */
    public function getCleanupStats(): array
    {
        return get_option('forooshyar_cleanup_stats', [
            'last_cleanup' => null,
            'total_cleanups' => 0,
            'total_logs_deleted' => 0,
            'total_rate_limits_deleted' => 0,
            'average_logs_per_cleanup' => 0
        ]);
    }

    /**
     * Update cleanup statistics
     */
    private function updateCleanupStats(array $result): void
    {
        $stats = $this->getCleanupStats();
        
        $stats['last_cleanup'] = current_time('mysql');
        $stats['total_cleanups']++;
        $stats['total_logs_deleted'] += $result['logs_deleted'];
        $stats['total_rate_limits_deleted'] += $result['rate_limits_deleted'];
        
        if ($stats['total_cleanups'] > 0) {
            $stats['average_logs_per_cleanup'] = round($stats['total_logs_deleted'] / $stats['total_cleanups'], 2);
        }
        
        update_option('forooshyar_cleanup_stats', $stats);
    }

    /**
     * Check if cleanup is needed based on log count
     */
    public function isCleanupNeeded(): bool
    {
        try {
            $metrics = $this->logService->getPerformanceMetrics();
            return $metrics['cleanup_needed'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get next scheduled cleanup time
     */
    public function getNextCleanupTime(): ?int
    {
        return wp_next_scheduled(self::CLEANUP_HOOK) ?: null;
    }

    /**
     * Reschedule cleanup with new interval
     */
    public function rescheduleCleanup(string $interval = 'daily'): bool
    {
        // Clear existing schedule
        $this->clearScheduledEvents();
        
        // Schedule with new interval
        return wp_schedule_event(time(), $interval, self::CLEANUP_HOOK) !== false;
    }
}