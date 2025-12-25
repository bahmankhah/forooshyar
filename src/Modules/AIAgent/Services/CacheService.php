<?php
/**
 * Cache Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

class CacheService
{
    const CACHE_GROUP = 'aiagent';
    const DEFAULT_EXPIRATION = 3600; // 1 hour

    /** @var SettingsManager */
    private $settings;

    /**
     * @param SettingsManager $settings
     */
    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get cached value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $value = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($value === false) {
            // Try transient as fallback
            $value = get_transient(self::CACHE_GROUP . '_' . $key);
        }

        return $value !== false ? $value : $default;
    }

    /**
     * Set cached value
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration Seconds until expiration
     * @return bool
     */
    public function set($key, $value, $expiration = self::DEFAULT_EXPIRATION)
    {
        // Set in object cache
        wp_cache_set($key, $value, self::CACHE_GROUP, $expiration);

        // Also set as transient for persistence
        return set_transient(self::CACHE_GROUP . '_' . $key, $value, $expiration);
    }

    /**
     * Delete cached value
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        wp_cache_delete($key, self::CACHE_GROUP);
        return delete_transient(self::CACHE_GROUP . '_' . $key);
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }

    /**
     * Get or set cached value
     *
     * @param string $key
     * @param callable $callback
     * @param int $expiration
     * @return mixed
     */
    public function remember($key, callable $callback, $expiration = self::DEFAULT_EXPIRATION)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $expiration);

        return $value;
    }

    /**
     * Flush all module cache
     *
     * @return bool
     */
    public function flush()
    {
        global $wpdb;

        // Clear object cache group
        wp_cache_flush();

        // Clear transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . self::CACHE_GROUP . '_%',
                '_transient_timeout_' . self::CACHE_GROUP . '_%'
            )
        );

        return true;
    }

    /**
     * Cache analysis result
     *
     * @param string $entityType
     * @param int $entityId
     * @param array $data
     * @param int $expiration
     * @return bool
     */
    public function cacheAnalysis($entityType, $entityId, array $data, $expiration = 86400)
    {
        $key = "analysis_{$entityType}_{$entityId}";
        return $this->set($key, $data, $expiration);
    }

    /**
     * Get cached analysis
     *
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    public function getAnalysis($entityType, $entityId)
    {
        $key = "analysis_{$entityType}_{$entityId}";
        return $this->get($key);
    }

    /**
     * Invalidate analysis cache
     *
     * @param string $entityType
     * @param int $entityId
     * @return bool
     */
    public function invalidateAnalysis($entityType, $entityId)
    {
        $key = "analysis_{$entityType}_{$entityId}";
        return $this->delete($key);
    }
}
