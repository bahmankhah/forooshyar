<?php
/**
 * Rate Limit Service
 * 
 * Manages API rate limiting for LLM calls.
 * When limits are exceeded, actions are rescheduled instead of failing.
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appLogger;

class RateLimitService
{
    /** @var SettingsManager */
    private $settings;

    /** @var CacheService */
    private $cache;

    /** @var int Default delay when rescheduling (seconds) */
    const RESCHEDULE_DELAY_HOUR = 3600;  // 1 hour
    const RESCHEDULE_DELAY_DAY = 86400;  // 24 hours

    /**
     * @param SettingsManager $settings
     * @param CacheService $cache
     */
    public function __construct(SettingsManager $settings, CacheService $cache)
    {
        $this->settings = $settings;
        $this->cache = $cache;
    }

    /**
     * Check if rate limit is exceeded
     *
     * @param string $type 'hour' or 'day'
     * @return bool
     */
    public function isLimitExceeded($type = 'hour')
    {
        $current = $this->getCurrentCount($type);
        $limit = $this->getLimit($type);

        return $current >= $limit;
    }

    /**
     * Check both hourly and daily limits
     *
     * @return array ['exceeded' => bool, 'type' => string|null, 'wait_seconds' => int]
     */
    public function checkLimits()
    {
        // Check hourly limit first
        if ($this->isLimitExceeded('hour')) {
            return [
                'exceeded' => true,
                'type' => 'hour',
                'wait_seconds' => $this->getResetTime('hour'),
            ];
        }

        // Check daily limit
        if ($this->isLimitExceeded('day')) {
            return [
                'exceeded' => true,
                'type' => 'day',
                'wait_seconds' => $this->getResetTime('day'),
            ];
        }

        return [
            'exceeded' => false,
            'type' => null,
            'wait_seconds' => 0,
        ];
    }

    /**
     * Increment rate limit counter
     *
     * @param string $type 'hour' or 'day'
     * @return int New count
     */
    public function increment($type = 'hour')
    {
        $key = $this->getKey($type);
        $current = $this->getCurrentCount($type);
        $newCount = $current + 1;

        $expiration = $type === 'hour' ? 3600 : 86400;
        $this->cache->set($key, $newCount, $expiration);

        return $newCount;
    }

    /**
     * Increment both hourly and daily counters
     *
     * @return void
     */
    public function incrementAll()
    {
        $this->increment('hour');
        $this->increment('day');
    }

    /**
     * Get current count
     *
     * @param string $type
     * @return int
     */
    public function getCurrentCount($type = 'hour')
    {
        $key = $this->getKey($type);
        return (int) $this->cache->get($key, 0);
    }

    /**
     * Get limit for type
     *
     * @param string $type
     * @return int
     */
    public function getLimit($type = 'hour')
    {
        if ($type === 'hour') {
            return (int) $this->settings->get('rate_limit_per_hour', 100);
        }
        return (int) $this->settings->get('rate_limit_per_day', 1000);
    }

    /**
     * Get remaining calls
     *
     * @param string $type
     * @return int
     */
    public function getRemaining($type = 'hour')
    {
        $limit = $this->getLimit($type);
        $current = $this->getCurrentCount($type);
        return max(0, $limit - $current);
    }

    /**
     * Get rate limit status
     *
     * @return array
     */
    public function getStatus()
    {
        return [
            'hourly' => [
                'limit' => $this->getLimit('hour'),
                'used' => $this->getCurrentCount('hour'),
                'remaining' => $this->getRemaining('hour'),
                'reset_in' => $this->getResetTime('hour'),
            ],
            'daily' => [
                'limit' => $this->getLimit('day'),
                'used' => $this->getCurrentCount('day'),
                'remaining' => $this->getRemaining('day'),
                'reset_in' => $this->getResetTime('day'),
            ],
        ];
    }

    /**
     * Reset rate limit counter
     *
     * @param string $type
     * @return bool
     */
    public function reset($type = 'hour')
    {
        $key = $this->getKey($type);
        return $this->cache->delete($key);
    }

    /**
     * Get cache key for rate limit
     *
     * @param string $type
     * @return string
     */
    private function getKey($type)
    {
        $period = $type === 'hour' ? date('Y-m-d-H') : date('Y-m-d');
        return "aiagent_rate_limit_{$type}_{$period}";
    }

    /**
     * Check limits and increment if allowed
     *
     * @return array ['allowed' => bool, 'reschedule_delay' => int|null]
     */
    public function checkAndIncrement()
    {
        $limits = $this->checkLimits();

        if ($limits['exceeded']) {
            appLogger("[AIAgent] Rate limit exceeded ({$limits['type']}), need to wait {$limits['wait_seconds']} seconds");
            
            return [
                'allowed' => false,
                'reschedule_delay' => $limits['wait_seconds'],
                'limit_type' => $limits['type'],
            ];
        }

        // Increment both counters
        $this->incrementAll();

        return [
            'allowed' => true,
            'reschedule_delay' => null,
            'limit_type' => null,
        ];
    }

    /**
     * Wait time until rate limit resets
     *
     * @param string $type
     * @return int Seconds until reset
     */
    public function getResetTime($type = 'hour')
    {
        if ($type === 'hour') {
            return 3600 - (time() % 3600);
        }
        
        $midnight = strtotime('tomorrow midnight');
        return $midnight - time();
    }

    /**
     * Get recommended reschedule timestamp based on current limits
     *
     * @return int Unix timestamp for when to reschedule
     */
    public function getRescheduleTimestamp()
    {
        $limits = $this->checkLimits();
        
        if (!$limits['exceeded']) {
            return time(); // Can run now
        }

        // Add a small buffer (5 minutes) to the wait time
        return time() + $limits['wait_seconds'] + 300;
    }

    /**
     * Log rate limit status
     *
     * @return void
     */
    public function logStatus()
    {
        $status = $this->getStatus();
        appLogger(sprintf(
            "[AIAgent] Rate limit status - Hourly: %d/%d, Daily: %d/%d",
            $status['hourly']['used'],
            $status['hourly']['limit'],
            $status['daily']['used'],
            $status['daily']['limit']
        ));
    }
}
