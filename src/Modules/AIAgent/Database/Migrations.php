<?php
/**
 * Database Migrations
 * 
 * @package Forooshyar\Modules\AIAgent\Database
 */

namespace Forooshyar\Modules\AIAgent\Database;

class Migrations
{
    /** @var string */
    private $charsetCollate;

    public function __construct()
    {
        global $wpdb;
        $this->charsetCollate = $wpdb->get_charset_collate();
    }

    /**
     * Run all migrations
     *
     * @return void
     */
    public function run()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->createAnalysisTable();
        $this->createActionsTable();
        $this->createContextTable();
        $this->createUsageTable();
        $this->createScheduledTable();

        update_option('aiagent_db_version', '1.0.0');
    }

    /**
     * Create analysis results table
     *
     * @return void
     */
    private function createAnalysisTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_analysis';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            analysis_type VARCHAR(50) NOT NULL,
            entity_id BIGINT(20) UNSIGNED NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            analysis_data LONGTEXT NOT NULL,
            suggestions LONGTEXT NOT NULL,
            priority_score TINYINT(3) UNSIGNED DEFAULT 0,
            status VARCHAR(20) DEFAULT 'completed',
            llm_provider VARCHAR(50) DEFAULT NULL,
            llm_model VARCHAR(100) DEFAULT NULL,
            tokens_used INT(11) DEFAULT 0,
            duration_ms INT(11) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_type_status (analysis_type, status),
            KEY idx_priority (priority_score),
            KEY idx_created (created_at)
        ) {$this->charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create actions table
     *
     * @return void
     */
    private function createActionsTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_actions';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            analysis_id BIGINT(20) UNSIGNED DEFAULT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_data LONGTEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            priority_score TINYINT(3) UNSIGNED DEFAULT 50,
            requires_approval TINYINT(1) DEFAULT 0,
            approved_by BIGINT(20) UNSIGNED DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            executed_at DATETIME DEFAULT NULL,
            result LONGTEXT DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            retry_count TINYINT(3) UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_analysis (analysis_id),
            KEY idx_type_status (action_type, status),
            KEY idx_priority (priority_score DESC),
            KEY idx_created (created_at)
        ) {$this->charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create context/prompts table
     *
     * @return void
     */
    private function createContextTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_context';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            context_key VARCHAR(100) NOT NULL,
            context_type VARCHAR(50) DEFAULT 'prompt',
            context_data LONGTEXT NOT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_context_key (context_key),
            KEY idx_type_active (context_type, is_active)
        ) {$this->charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create usage tracking table
     *
     * @return void
     */
    private function createUsageTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_usage';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            usage_type VARCHAR(50) NOT NULL,
            usage_date DATE NOT NULL,
            count INT(11) UNSIGNED DEFAULT 0,
            metadata LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_type_date (usage_type, usage_date),
            KEY idx_date (usage_date)
        ) {$this->charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Create scheduled tasks table
     *
     * @return void
     */
    private function createScheduledTable()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_type VARCHAR(50) NOT NULL,
            task_data LONGTEXT NOT NULL,
            scheduled_at DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            executed_at DATETIME DEFAULT NULL,
            result LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_scheduled (scheduled_at, status),
            KEY idx_type (task_type)
        ) {$this->charsetCollate};";

        dbDelta($sql);
    }

    /**
     * Drop all tables (for uninstall)
     *
     * @return void
     */
    public function drop()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'aiagent_analysis',
            $wpdb->prefix . 'aiagent_actions',
            $wpdb->prefix . 'aiagent_context',
            $wpdb->prefix . 'aiagent_usage',
            $wpdb->prefix . 'aiagent_scheduled',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('aiagent_db_version');
    }
}
