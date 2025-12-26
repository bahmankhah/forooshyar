<?php
/**
 * Database Schema Helper
 * 
 * @package Forooshyar\Modules\AIAgent\Database
 */

namespace Forooshyar\Modules\AIAgent\Database;

class Schema
{
    /**
     * Get table names
     *
     * @return array
     */
    public static function getTables()
    {
        global $wpdb;

        return [
            'analysis' => $wpdb->prefix . 'forooshyar_aiagent_analysis',
            'actions' => $wpdb->prefix . 'forooshyar_aiagent_actions',
            'context' => $wpdb->prefix . 'forooshyar_aiagent_context',
            'usage' => $wpdb->prefix . 'forooshyar_aiagent_usage',
            // Note: 'scheduled' table removed - now using WooCommerce Action Scheduler
        ];
    }

    /**
     * Check if tables exist
     *
     * @return bool
     */
    public static function tablesExist()
    {
        global $wpdb;
        $tables = self::getTables();

        foreach ($tables as $table) {
            $result = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($result !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get current database version
     *
     * @return string
     */
    public static function getVersion()
    {
        return get_option('aiagent_db_version', '0.0.0');
    }

    /**
     * Check if migration is needed
     *
     * @return bool
     */
    public static function needsMigration()
    {
        return version_compare(self::getVersion(), '1.1.0', '<');
    }
}
