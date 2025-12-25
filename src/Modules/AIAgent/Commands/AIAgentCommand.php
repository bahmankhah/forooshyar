<?php
/**
 * AI Agent WP-CLI Commands
 * 
 * @package Forooshyar\Modules\AIAgent\Commands
 */

namespace Forooshyar\Modules\AIAgent\Commands;

use WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\AIAgentService;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;
use Forooshyar\Modules\AIAgent\Services\ActionExecutor;
use Forooshyar\Modules\AIAgent\Services\DatabaseService;
use Forooshyar\Modules\AIAgent\Database\Migrations;

/**
 * Manage AI Sales Agent module
 */
class AIAgentCommand
{
    /**
     * Show module status and subscription info
     *
     * ## EXAMPLES
     *     wp aiagent status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args)
    {
        $service = Container::resolve(AIAgentService::class);
        $status = $service->getStatus();

        \WP_CLI::line('AI Agent Module Status');
        \WP_CLI::line('----------------------');
        \WP_CLI::line('Enabled: ' . ($status['enabled'] ? 'Yes' : 'No'));
        \WP_CLI::line('Tier: ' . ucfirst($status['tier']));
        \WP_CLI::line('LLM Provider: ' . $status['llm_provider']);
        \WP_CLI::line('LLM Model: ' . $status['llm_model']);
        \WP_CLI::line('Features: ' . implode(', ', $status['features']));
    }

    /**
     * Enable the AI Agent module
     *
     * ## EXAMPLES
     *     wp aiagent enable
     *
     * @when after_wp_load
     */
    public function enable($args, $assoc_args)
    {
        update_option('aiagent_module_enabled', true);
        \WP_CLI::success('AI Agent module enabled');
    }

    /**
     * Disable the AI Agent module
     *
     * ## EXAMPLES
     *     wp aiagent disable
     *
     * @when after_wp_load
     */
    public function disable($args, $assoc_args)
    {
        update_option('aiagent_module_enabled', false);
        \WP_CLI::success('AI Agent module disabled');
    }

    /**
     * Run analysis
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Analysis type (all, products, customers)
     * ---
     * default: all
     * ---
     *
     * [--limit=<limit>]
     * : Limit entities to analyze
     *
     * ## EXAMPLES
     *     wp aiagent analyze
     *     wp aiagent analyze --type=products
     *     wp aiagent analyze --type=customers --limit=10
     *
     * @when after_wp_load
     */
    public function analyze($args, $assoc_args)
    {
        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'all';
        
        $service = Container::resolve(AIAgentService::class);

        \WP_CLI::line("Running {$type} analysis...");

        try {
            $result = $service->runAnalysis($type);

            if ($result['success']) {
                \WP_CLI::success('Analysis completed');
                
                if ($result['products']) {
                    \WP_CLI::line("Products analyzed: {$result['products']['analyzed']}/{$result['products']['total']}");
                    \WP_CLI::line("Suggestions generated: " . count($result['products']['suggestions']));
                }
                
                if ($result['customers']) {
                    \WP_CLI::line("Customers analyzed: {$result['customers']['analyzed']}/{$result['customers']['total']}");
                    \WP_CLI::line("Suggestions generated: " . count($result['customers']['suggestions']));
                }
            } else {
                \WP_CLI::error('Analysis failed: ' . implode(', ', $result['errors']));
            }
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    /**
     * Manage actions
     *
     * ## OPTIONS
     *
     * <subcommand>
     * : Subcommand (list, execute, approve, cancel)
     *
     * [--id=<id>]
     * : Action ID for execute/approve/cancel
     *
     * [--status=<status>]
     * : Filter by status for list
     *
     * ## EXAMPLES
     *     wp aiagent actions list
     *     wp aiagent actions list --status=pending
     *     wp aiagent actions execute --id=123
     *     wp aiagent actions approve --id=123
     *
     * @when after_wp_load
     */
    public function actions($args, $assoc_args)
    {
        $subcommand = isset($args[0]) ? $args[0] : 'list';
        $db = Container::resolve(DatabaseService::class);
        $executor = Container::resolve(ActionExecutor::class);

        switch ($subcommand) {
            case 'list':
                $filters = [];
                if (isset($assoc_args['status'])) {
                    $filters['status'] = $assoc_args['status'];
                }
                
                $actions = $db->getActions($filters, 50);
                
                if (empty($actions)) {
                    \WP_CLI::line('No actions found');
                    return;
                }

                $table = [];
                foreach ($actions as $action) {
                    $table[] = [
                        'ID' => $action['id'],
                        'Type' => $action['action_type'],
                        'Status' => $action['status'],
                        'Priority' => $action['priority_score'],
                        'Created' => $action['created_at'],
                    ];
                }
                
                \WP_CLI\Utils\format_items('table', $table, ['ID', 'Type', 'Status', 'Priority', 'Created']);
                break;

            case 'execute':
                if (!isset($assoc_args['id'])) {
                    \WP_CLI::error('--id is required');
                    return;
                }
                
                $result = $executor->executeById((int) $assoc_args['id']);
                
                if ($result['success']) {
                    \WP_CLI::success($result['message']);
                } else {
                    \WP_CLI::error($result['message']);
                }
                break;

            case 'approve':
                if (!isset($assoc_args['id'])) {
                    \WP_CLI::error('--id is required');
                    return;
                }
                
                $db->approveAction((int) $assoc_args['id'], 0);
                \WP_CLI::success('Action approved');
                break;

            case 'cancel':
                if (!isset($assoc_args['id'])) {
                    \WP_CLI::error('--id is required');
                    return;
                }
                
                $db->updateActionStatus((int) $assoc_args['id'], 'cancelled');
                \WP_CLI::success('Action cancelled');
                break;

            default:
                \WP_CLI::error("Unknown subcommand: {$subcommand}");
        }
    }

    /**
     * Show statistics
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to show stats for
     * ---
     * default: 30
     * ---
     *
     * [--format=<format>]
     * : Output format (table, json)
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *     wp aiagent stats
     *     wp aiagent stats --days=7
     *     wp aiagent stats --format=json
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args)
    {
        $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : 30;
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';

        $service = Container::resolve(AIAgentService::class);
        $stats = $service->getStatistics($days);

        if ($format === 'json') {
            \WP_CLI::line(wp_json_encode($stats, JSON_PRETTY_PRINT));
            return;
        }

        \WP_CLI::line("Statistics (Last {$days} days)");
        \WP_CLI::line('----------------------------');
        \WP_CLI::line("Total Analyses: {$stats['total_analyses']}");
        \WP_CLI::line("Total Actions: {$stats['total_actions']}");
        \WP_CLI::line("Pending Actions: {$stats['summary']['pending_actions']}");
        \WP_CLI::line("Success Rate: {$stats['summary']['success_rate']}%");
        \WP_CLI::line("Analyses Today: {$stats['summary']['analyses_today']}");
        \WP_CLI::line("Completed Today: {$stats['summary']['completed_today']}");
    }

    /**
     * Manage settings
     *
     * ## OPTIONS
     *
     * <subcommand>
     * : Subcommand (list, get, set, reset)
     *
     * [<key>]
     * : Setting key for get/set
     *
     * [<value>]
     * : Setting value for set
     *
     * ## EXAMPLES
     *     wp aiagent settings list
     *     wp aiagent settings get llm_provider
     *     wp aiagent settings set llm_provider openai
     *     wp aiagent settings reset
     *
     * @when after_wp_load
     */
    public function settings($args, $assoc_args)
    {
        $subcommand = isset($args[0]) ? $args[0] : 'list';
        $settings = Container::resolve(SettingsManager::class);

        switch ($subcommand) {
            case 'list':
                $all = $settings->all();
                $table = [];
                foreach ($all as $key => $value) {
                    $table[] = [
                        'Key' => $key,
                        'Value' => is_array($value) ? implode(', ', $value) : $value,
                    ];
                }
                \WP_CLI\Utils\format_items('table', $table, ['Key', 'Value']);
                break;

            case 'get':
                if (!isset($args[1])) {
                    \WP_CLI::error('Key is required');
                    return;
                }
                $value = $settings->get($args[1]);
                \WP_CLI::line(is_array($value) ? wp_json_encode($value) : $value);
                break;

            case 'set':
                if (!isset($args[1]) || !isset($args[2])) {
                    \WP_CLI::error('Key and value are required');
                    return;
                }
                $settings->set($args[1], $args[2]);
                \WP_CLI::success("Setting '{$args[1]}' updated");
                break;

            case 'reset':
                $settings->reset();
                \WP_CLI::success('Settings reset to defaults');
                break;

            default:
                \WP_CLI::error("Unknown subcommand: {$subcommand}");
        }
    }

    /**
     * Test LLM connection
     *
     * ## OPTIONS
     *
     * [--verbose]
     * : Show verbose output
     *
     * ## EXAMPLES
     *     wp aiagent test-llm
     *     wp aiagent test-llm --verbose
     *
     * @when after_wp_load
     * @subcommand test-llm
     */
    public function test_llm($args, $assoc_args)
    {
        $service = Container::resolve(AIAgentService::class);
        $result = $service->testConnection();

        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }

    /**
     * Clean old analysis data
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Delete data older than this many days
     * ---
     * default: 90
     * ---
     *
     * ## EXAMPLES
     *     wp aiagent cleanup
     *     wp aiagent cleanup --days=30
     *
     * @when after_wp_load
     */
    public function cleanup($args, $assoc_args)
    {
        $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : 90;
        $db = Container::resolve(DatabaseService::class);

        $deleted = $db->cleanup($days);
        \WP_CLI::success("Cleaned up {$deleted} old records");
    }

    /**
     * Run database migrations
     *
     * ## EXAMPLES
     *     wp aiagent migrate
     *
     * @when after_wp_load
     */
    public function migrate($args, $assoc_args)
    {
        $migrations = new Migrations();
        $migrations->run();
        \WP_CLI::success('Database migrations completed');
    }
}
