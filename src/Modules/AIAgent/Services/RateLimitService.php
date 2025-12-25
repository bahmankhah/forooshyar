<?php
/**
 * Rate Limit Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

class RateLimitService
{
    /** @var SettingsManager */
    private $settings;

    /** @var CacheService */
    private $cache;

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
            ],
            'daily' => [
                'limit' => $this->getLimit('day'),
                'used' => $this->getCurrentCount('day'),
                'remaining' => $this->getRemaining('day'),
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
        return "rate_limit_{$type}_{$period}";
    }

    /**
     * Check and increment if allowed
     *
     * @param string $type
     * @return bool True if allowed, false if limit exceeded
     */
    public function checkAndIncrement($type = 'hour')
    {
        if ($this->isLimitExceeded($type)) {
            return false;
        }

        $this->increment($type);
        return true;
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
}
