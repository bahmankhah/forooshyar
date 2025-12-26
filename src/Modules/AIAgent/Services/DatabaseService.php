<?php
/**
 * Database Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appLogger;

class DatabaseService
{
    /** @var string */
    private $analysisTable;

    /** @var string */
    private $actionsTable;

    /** @var string */
    private $contextTable;

    /** @var string */
    private $usageTable;

    /** @var string */
    private $scheduledTable;

    public function __construct()
    {
        global $wpdb;
        $this->analysisTable = $wpdb->prefix . 'aiagent_analysis';
        $this->actionsTable = $wpdb->prefix . 'aiagent_actions';
        $this->contextTable = $wpdb->prefix . 'aiagent_context';
        $this->usageTable = $wpdb->prefix . 'aiagent_usage';
        $this->scheduledTable = $wpdb->prefix . 'aiagent_scheduled';
    }

    /**
     * Save analysis result
     *
     * @param array $data
     * @return int|false Insert ID or false on failure
     */
    public function saveAnalysis(array $data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->analysisTable,
            [
                'analysis_type' => $data['analysis_type'],
                'entity_id' => $data['entity_id'],
                'entity_type' => $data['entity_type'],
                'analysis_data' => wp_json_encode($data['analysis_data']),
                'suggestions' => wp_json_encode($data['suggestions']),
                'priority_score' => isset($data['priority_score']) ? $data['priority_score'] : 0,
                'status' => isset($data['status']) ? $data['status'] : 'completed',
                'llm_provider' => isset($data['llm_provider']) ? $data['llm_provider'] : null,
                'llm_model' => isset($data['llm_model']) ? $data['llm_model'] : null,
                'tokens_used' => isset($data['tokens_used']) ? $data['tokens_used'] : 0,
                'duration_ms' => isset($data['duration_ms']) ? $data['duration_ms'] : 0,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get analysis by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getAnalysis($id)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->analysisTable} WHERE id = %d", $id),
            ARRAY_A
        );

        if ($row) {
            $row['analysis_data'] = json_decode($row['analysis_data'], true);
            $row['suggestions'] = json_decode($row['suggestions'], true);
        }

        return $row;
    }

    /**
     * Get analyses with filters
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAnalyses(array $filters = [], $limit = 20, $offset = 0)
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['analysis_type'])) {
            $where[] = 'analysis_type = %s';
            $params[] = $filters['analysis_type'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = %s';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['min_priority'])) {
            $where[] = 'priority_score >= %d';
            $params[] = $filters['min_priority'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->analysisTable} WHERE {$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        foreach ($rows as &$row) {
            $row['analysis_data'] = json_decode($row['analysis_data'], true);
            $row['suggestions'] = json_decode($row['suggestions'], true);
        }

        return $rows;
    }

    /**
     * Save action (with deduplication - removes older pending actions of same type for same entity)
     *
     * @param array $data
     * @return int|false Insert ID or false on failure
     */
    public function saveAction(array $data)
    {
        global $wpdb;

        // Extract entity info from action_data for deduplication
        $actionData = isset($data['action_data']) ? $data['action_data'] : [];
        $entityId = isset($actionData['entity_id']) ? $actionData['entity_id'] : null;
        $entityType = isset($actionData['entity_type']) ? $actionData['entity_type'] : null;
        
        // If no entity info in action_data, try to get from product_id or customer_id
        if (!$entityId) {
            if (isset($actionData['product_id'])) {
                $entityId = $actionData['product_id'];
                $entityType = 'product';
            } elseif (isset($actionData['customer_id'])) {
                $entityId = $actionData['customer_id'];
                $entityType = 'customer';
            }
        }

        // Remove duplicate pending/approved actions for same entity and action type
        if ($entityId && $entityType) {
            $this->removeDuplicatePendingActions(
                $data['action_type'],
                $entityId,
                $entityType
            );
        }

        $result = $wpdb->insert(
            $this->actionsTable,
            [
                'analysis_id' => isset($data['analysis_id']) ? $data['analysis_id'] : null,
                'action_type' => $data['action_type'],
                'action_data' => wp_json_encode($data['action_data']),
                'status' => isset($data['status']) ? $data['status'] : 'pending',
                'priority_score' => isset($data['priority_score']) ? $data['priority_score'] : 50,
                'requires_approval' => isset($data['requires_approval']) ? $data['requires_approval'] : 0,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Remove duplicate pending/approved actions for same entity and action type
     * This ensures only the newest action suggestion is kept
     *
     * @param string $actionType
     * @param int $entityId
     * @param string $entityType
     * @return int Number of deleted rows
     */
    public function removeDuplicatePendingActions($actionType, $entityId, $entityType)
    {
        global $wpdb;

        // Build JSON patterns to match entity in action_data
        // We need to match actions that have this entity_id/entity_type OR product_id/customer_id
        $patterns = [];
        
        if ($entityType === 'product') {
            $patterns[] = $wpdb->prepare(
                "(action_data LIKE %s OR action_data LIKE %s)",
                '%"entity_id":' . $entityId . '%',
                '%"product_id":' . $entityId . '%'
            );
        } elseif ($entityType === 'customer') {
            $patterns[] = $wpdb->prepare(
                "(action_data LIKE %s OR action_data LIKE %s)",
                '%"entity_id":' . $entityId . '%',
                '%"customer_id":' . $entityId . '%'
            );
        }

        if (empty($patterns)) {
            return 0;
        }

        $patternClause = implode(' OR ', $patterns);

        // Delete pending or approved actions of same type for same entity
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->actionsTable} 
             WHERE action_type = %s 
             AND status IN ('pending', 'approved')
             AND ({$patternClause})",
            $actionType
        ));

        if ($deleted > 0) {
            appLogger("[AIAgent] Removed {$deleted} duplicate pending action(s) for {$entityType} #{$entityId}, type: {$actionType}");
        }

        return $deleted ? $deleted : 0;
    }

    /**
     * Get action by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getAction($id)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->actionsTable} WHERE id = %d", $id),
            ARRAY_A
        );

        if ($row) {
            $row['action_data'] = json_decode($row['action_data'], true);
            $row['result'] = $row['result'] ? json_decode($row['result'], true) : null;
        }

        return $row;
    }

    /**
     * Get actions with filters
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getActions(array $filters = [], $limit = 20, $offset = 0)
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = %s';
            $params[] = $filters['action_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (isset($filters['requires_approval'])) {
            $where[] = 'requires_approval = %d';
            $params[] = $filters['requires_approval'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->actionsTable} WHERE {$whereClause} ORDER BY priority_score DESC, created_at DESC LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        foreach ($rows as &$row) {
            $row['action_data'] = json_decode($row['action_data'], true);
            $row['result'] = $row['result'] ? json_decode($row['result'], true) : null;
        }

        return $rows;
    }

    /**
     * Update action status
     *
     * @param int $id
     * @param string $status
     * @param array $result
     * @return bool
     */
    public function updateActionStatus($id, $status, array $result = null)
    {
        global $wpdb;

        $data = ['status' => $status];
        $format = ['%s'];

        if ($status === 'completed' || $status === 'failed') {
            $data['executed_at'] = current_time('mysql');
            $format[] = '%s';
        }

        if ($result !== null) {
            $data['result'] = wp_json_encode($result);
            $format[] = '%s';
        }

        return (bool) $wpdb->update(
            $this->actionsTable,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );
    }

    /**
     * Approve action
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function approveAction($id, $userId)
    {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->actionsTable,
            [
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * Delete action permanently
     *
     * @param int $id
     * @return bool
     */
    public function deleteAction($id)
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->actionsTable,
            ['id' => $id],
            ['%d']
        );
    }

    /**
     * Delete multiple actions by status
     *
     * @param array $statuses
     * @return int Number of deleted rows
     */
    public function deleteActionsByStatus(array $statuses)
    {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        
        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->actionsTable} WHERE status IN ({$placeholders})",
                $statuses
            )
        );
    }

    /**
     * Approve all pending actions
     *
     * @param int $userId
     * @return int Number of approved actions
     */
    public function approveAllPendingActions($userId)
    {
        global $wpdb;

        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->actionsTable} 
                 SET status = 'approved', approved_by = %d, approved_at = %s 
                 WHERE status = 'pending'",
                $userId,
                current_time('mysql')
            )
        );
    }

    /**
     * Increment retry count
     *
     * @param int $id
     * @param string $errorMessage
     * @return bool
     */
    public function incrementRetry($id, $errorMessage = null)
    {
        global $wpdb;

        $data = [];
        $format = [];

        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
            $format[] = '%s';
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->actionsTable} SET retry_count = retry_count + 1 WHERE id = %d",
            $id
        ));

        if (!empty($data)) {
            $wpdb->update($this->actionsTable, $data, ['id' => $id], $format, ['%d']);
        }

        return true;
    }

    /**
     * Get pending actions count
     *
     * @return int
     */
    public function getPendingActionsCount()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->actionsTable} WHERE status IN ('pending', 'approved')");
    }

    /**
     * Get today's completed actions count
     *
     * @return int
     */
    public function getTodayCompletedCount()
    {
        global $wpdb;
        $today = current_time('Y-m-d');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->actionsTable} WHERE status = 'completed' AND DATE(executed_at) = %s",
            $today
        ));
    }

    /**
     * Get success rate
     *
     * @param int $days
     * @return float
     */
    public function getSuccessRate($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->actionsTable} WHERE executed_at >= %s",
            $date
        ));

        if ($total === 0) {
            return 100.0;
        }

        $successful = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->actionsTable} WHERE status = 'completed' AND executed_at >= %s",
            $date
        ));

        return round(($successful / $total) * 100, 1);
    }

    /**
     * Get today's analyses count
     *
     * @return int
     */
    public function getTodayAnalysesCount()
    {
        global $wpdb;
        $today = current_time('Y-m-d');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->analysisTable} WHERE DATE(created_at) = %s",
            $today
        ));
    }

    /**
     * Get statistics for dashboard
     *
     * @param int $days
     * @return array
     */
    public function getStatistics($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        // Daily analysis counts
        $analysisDaily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$this->analysisTable} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            $date
        ), ARRAY_A);

        // Daily action counts
        $actionsDaily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$this->actionsTable} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date",
            $date
        ), ARRAY_A);

        // Action types distribution
        $actionTypes = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as count 
             FROM {$this->actionsTable} 
             WHERE created_at >= %s 
             GROUP BY action_type",
            $date
        ), ARRAY_A);

        return [
            'analysis_daily' => $analysisDaily,
            'actions_daily' => $actionsDaily,
            'action_types' => $actionTypes,
            'total_analyses' => array_sum(array_column($analysisDaily, 'count')),
            'total_actions' => array_sum(array_column($actionsDaily, 'count')),
        ];
    }

    /**
     * Clean old data
     *
     * @param int $days
     * @return int Number of rows deleted
     */
    public function cleanup($days = 90)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        $analysisDeleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->analysisTable} WHERE created_at < %s",
            $date
        ));

        $actionsDeleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->actionsTable} WHERE created_at < %s AND status IN ('completed', 'failed', 'cancelled')",
            $date
        ));

        $usageDeleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->usageTable} WHERE usage_date < %s",
            $date
        ));

        return $analysisDeleted + $actionsDeleted + $usageDeleted;
    }

    /**
     * Save analysis run record
     *
     * @param array $data
     * @return int|false
     */
    public function saveAnalysisRun(array $data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->analysisTable,
            [
                'analysis_type' => isset($data['type']) ? $data['type'] : 'run',
                'entity_id' => 0,
                'entity_type' => 'run',
                'analysis_data' => wp_json_encode([
                    'products_analyzed' => isset($data['products_analyzed']) ? $data['products_analyzed'] : 0,
                    'customers_analyzed' => isset($data['customers_analyzed']) ? $data['customers_analyzed'] : 0,
                    'actions_created' => isset($data['actions_created']) ? $data['actions_created'] : 0,
                    'actions_executed' => isset($data['actions_executed']) ? $data['actions_executed'] : 0,
                    'duration_ms' => isset($data['duration_ms']) ? $data['duration_ms'] : 0,
                ]),
                'suggestions' => wp_json_encode([]),
                'priority_score' => 0,
                'status' => isset($data['success']) && $data['success'] ? 'completed' : 'failed',
                'tokens_used' => 0,
                'duration_ms' => isset($data['duration_ms']) ? $data['duration_ms'] : 0,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Check if database tables exist
     *
     * @return bool
     */
    public function checkTablesExist()
    {
        global $wpdb;

        $tables = [
            $this->analysisTable,
            $this->actionsTable,
        ];

        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));

            if (!$exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get total analyses count
     *
     * @return int
     */
    public function getTotalAnalysesCount()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->analysisTable}");
    }

    /**
     * Get total actions count
     *
     * @return int
     */
    public function getTotalActionsCount()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->actionsTable}");
    }

    /**
     * Get today's actions count
     *
     * @return int
     */
    public function getTodayActionsCount()
    {
        global $wpdb;
        $today = current_time('Y-m-d');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->actionsTable} WHERE DATE(created_at) = %s",
            $today
        ));
    }

    /**
     * Get recent analyses
     *
     * @param int $limit
     * @return array
     */
    public function getRecentAnalyses($limit = 5)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->analysisTable} 
             WHERE entity_type != 'run'
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['analysis_data'] = json_decode($row['analysis_data'], true);
            $row['suggestions'] = json_decode($row['suggestions'], true);
        }

        return $rows;
    }

    /**
     * Get recent actions
     *
     * @param int $limit
     * @return array
     */
    public function getRecentActions($limit = 10)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->actionsTable} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['action_data'] = json_decode($row['action_data'], true);
            $row['result'] = $row['result'] ? json_decode($row['result'], true) : null;
        }

        return $rows;
    }

    /**
     * Get paginated actions
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPaginatedActions(array $filters = [], $page = 1, $perPage = 20)
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                $where[] = "status IN ({$placeholders})";
                $params = array_merge($params, $filters['status']);
            } else {
                $where[] = 'status = %s';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = %s';
            $params[] = $filters['action_type'];
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$this->actionsTable} WHERE {$whereClause}";
        $total = (int) $wpdb->get_var(
            !empty($params) ? $wpdb->prepare($countSql, $params) : $countSql
        );

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->actionsTable} WHERE {$whereClause} ORDER BY priority_score DESC, created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        foreach ($rows as &$row) {
            $row['action_data'] = json_decode($row['action_data'], true);
            $row['result'] = $row['result'] ? json_decode($row['result'], true) : null;
        }

        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get paginated analyses
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPaginatedAnalyses(array $filters = [], $page = 1, $perPage = 20)
    {
        global $wpdb;

        $where = ["entity_type != 'run'"];
        $params = [];

        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = %s';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['analysis_type'])) {
            $where[] = 'analysis_type = %s';
            $params[] = $filters['analysis_type'];
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$this->analysisTable} WHERE {$whereClause}";
        $total = (int) $wpdb->get_var(
            !empty($params) ? $wpdb->prepare($countSql, $params) : $countSql
        );

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $sql = "SELECT * FROM {$this->analysisTable} WHERE {$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        foreach ($rows as &$row) {
            $row['analysis_data'] = json_decode($row['analysis_data'], true);
            $row['suggestions'] = json_decode($row['suggestions'], true);
        }

        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get actions count by status
     *
     * @return array
     */
    public function getActionsCountByStatus()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->actionsTable} GROUP BY status",
            ARRAY_A
        );

        $counts = [
            'pending' => 0,
            'approved' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'all' => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
            $counts['all'] += (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get analyses count by type
     *
     * @return array
     */
    public function getAnalysesCountByType()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT entity_type, COUNT(*) as count FROM {$this->analysisTable} WHERE entity_type != 'run' GROUP BY entity_type",
            ARRAY_A
        );

        $counts = [
            'product' => 0,
            'customer' => 0,
            'all' => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['entity_type']] = (int) $row['count'];
            $counts['all'] += (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get analyses by day
     *
     * @param int $days
     * @return array
     */
    public function getAnalysesByDay($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$this->analysisTable} 
             WHERE created_at >= %s AND entity_type != 'run'
             GROUP BY DATE(created_at) 
             ORDER BY date",
            $date
        ), ARRAY_A);
    }

    /**
     * Get actions by type
     *
     * @param int $days
     * @return array
     */
    public function getActionsByType($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, status, COUNT(*) as count 
             FROM {$this->actionsTable} 
             WHERE created_at >= %s 
             GROUP BY action_type, status",
            $date
        ), ARRAY_A);
    }

    /**
     * Get success rate by day
     *
     * @param int $days
     * @return array
     */
    public function getSuccessByDay($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(executed_at) as date,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$this->actionsTable} 
             WHERE executed_at >= %s 
             GROUP BY DATE(executed_at) 
             ORDER BY date",
            $date
        ), ARRAY_A);
    }

    /**
     * Get total tokens used
     *
     * @param int $days
     * @return int
     */
    public function getTotalTokensUsed($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(tokens_used) FROM {$this->analysisTable} WHERE created_at >= %s",
            $date
        ));
    }

    /**
     * Get average response time
     *
     * @param int $days
     * @return float
     */
    public function getAvgResponseTime($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(duration_ms) FROM {$this->analysisTable} WHERE created_at >= %s AND duration_ms > 0",
            $date
        ));

        return $avg ? round((float) $avg, 2) : 0;
    }
}
