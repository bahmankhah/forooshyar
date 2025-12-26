<?php
/**
 * Scheduled Task Service
 * 
 * مدیریت وظایف زمان‌بندی شده مانند تغییرات قیمت، پیگیری‌ها و کمپین‌ها
 * این سرویس از جدول aiagent_scheduled برای ذخیره و اجرای وظایف استفاده می‌کند
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appLogger;

class ScheduledTaskService
{
    const CRON_HOOK = 'aiagent_process_scheduled_tasks';
    
    // Task types
    const TASK_PRICE_CHANGE = 'price_change';
    const TASK_FOLLOWUP = 'followup';
    const TASK_CAMPAIGN = 'campaign';
    const TASK_DISCOUNT_EXPIRY = 'discount_expiry';
    const TASK_INVENTORY_CHECK = 'inventory_check';
    const TASK_ANALYSIS_JOB = 'analysis_job';
    
    // Task statuses
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /** @var string */
    private $table;

    /** @var ActionExecutor */
    private $actionExecutor;

    /** @var SettingsManager */
    private $settings;

    /** @var Logger */
    private $logger;

    /**
     * @param ActionExecutor $actionExecutor
     * @param SettingsManager $settings
     * @param Logger $logger
     */
    public function __construct(
        ActionExecutor $actionExecutor,
        SettingsManager $settings,
        Logger $logger
    ) {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aiagent_scheduled';
        $this->actionExecutor = $actionExecutor;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    public function register()
    {
        add_action(self::CRON_HOOK, [$this, 'processDueTasks']);
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Schedule a new task
     *
     * @param string $type Task type
     * @param array $data Task data
     * @param string $scheduledAt DateTime string (Y-m-d H:i:s)
     * @return int|false Task ID or false on failure
     */
    public function schedule($type, array $data, $scheduledAt)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'task_type' => $type,
                'task_data' => wp_json_encode($data),
                'scheduled_at' => $scheduledAt,
                'status' => self::STATUS_PENDING,
            ],
            ['%s', '%s', '%s', '%s']
        );

        if ($result) {
            $taskId = $wpdb->insert_id;
            appLogger("[AIAgent] Task scheduled: {$type} (ID: {$taskId}) for {$scheduledAt}");
            return $taskId;
        }

        return false;
    }

    /**
     * Schedule a price change
     *
     * @param int $productId
     * @param float $newPrice
     * @param string $scheduledAt
     * @param float|null $revertPrice Price to revert to (optional)
     * @param string|null $revertAt DateTime to revert (optional)
     * @param string|null $reason
     * @return int|false
     */
    public function schedulePriceChange($productId, $newPrice, $scheduledAt, $revertPrice = null, $revertAt = null, $reason = null)
    {
        $data = [
            'product_id' => $productId,
            'new_price' => $newPrice,
            'reason' => $reason,
        ];

        $taskId = $this->schedule(self::TASK_PRICE_CHANGE, $data, $scheduledAt);

        // Schedule revert if specified
        if ($taskId && $revertPrice !== null && $revertAt !== null) {
            $revertData = [
                'product_id' => $productId,
                'new_price' => $revertPrice,
                'reason' => __('بازگشت قیمت به حالت قبل', 'forooshyar'),
                'original_task_id' => $taskId,
            ];
            $this->schedule(self::TASK_PRICE_CHANGE, $revertData, $revertAt);
        }

        return $taskId;
    }

    /**
     * Schedule a followup action
     *
     * @param string $followupType 'email' or 'sms'
     * @param int|null $customerId
     * @param int|null $productId
     * @param string $message
     * @param string $scheduledAt
     * @return int|false
     */
    public function scheduleFollowup($followupType, $customerId, $productId, $message, $scheduledAt)
    {
        $data = [
            'followup_type' => $followupType,
            'customer_id' => $customerId,
            'product_id' => $productId,
            'message' => $message,
        ];

        return $this->schedule(self::TASK_FOLLOWUP, $data, $scheduledAt);
    }

    /**
     * Schedule a campaign
     *
     * @param string $campaignName
     * @param string $campaignMessage
     * @param string $targetAudience
     * @param string $scheduledAt
     * @param int $durationDays
     * @return int|false
     */
    public function scheduleCampaign($campaignName, $campaignMessage, $targetAudience, $scheduledAt, $durationDays = 7)
    {
        $data = [
            'campaign_name' => $campaignName,
            'campaign_message' => $campaignMessage,
            'target_audience' => $targetAudience,
            'duration_days' => $durationDays,
        ];

        return $this->schedule(self::TASK_CAMPAIGN, $data, $scheduledAt);
    }

    /**
     * Get task by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getTask($id)
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if ($row) {
            $row['task_data'] = json_decode($row['task_data'], true);
            $row['result'] = $row['result'] ? json_decode($row['result'], true) : null;
        }

        return $row;
    }

    /**
     * Get pending tasks
     *
     * @param string|null $type Filter by type
     * @param int $limit
     * @return array
     */
    public function getPendingTasks($type = null, $limit = 100)
    {
        global $wpdb;

        $where = "status = %s";
        $params = [self::STATUS_PENDING];

        if ($type !== null) {
            $where .= " AND task_type = %s";
            $params[] = $type;
        }

        $params[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY scheduled_at ASC LIMIT %d",
            $params
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['task_data'] = json_decode($row['task_data'], true);
        }

        return $rows;
    }

    /**
     * Get due tasks (scheduled time has passed)
     *
     * @param int $limit
     * @return array
     */
    public function getDueTasks($limit = 50)
    {
        global $wpdb;
        $now = current_time('mysql');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = %s AND scheduled_at <= %s 
             ORDER BY scheduled_at ASC 
             LIMIT %d",
            self::STATUS_PENDING,
            $now,
            $limit
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['task_data'] = json_decode($row['task_data'], true);
        }

        return $rows;
    }

    /**
     * Process all due tasks
     *
     * @return array ['processed' => int, 'success' => int, 'failed' => int]
     */
    public function processDueTasks()
    {
        $tasks = $this->getDueTasks();
        $processed = 0;
        $success = 0;
        $failed = 0;

        appLogger("[AIAgent] Processing " . count($tasks) . " due scheduled tasks");

        foreach ($tasks as $task) {
            $result = $this->executeTask($task);
            $processed++;

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }
        }

        appLogger("[AIAgent] Scheduled tasks processed: {$success} success, {$failed} failed");

        return [
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Execute a single task
     *
     * @param array $task
     * @return array
     */
    public function executeTask(array $task)
    {
        $taskId = $task['id'];
        $taskType = $task['task_type'];
        $taskData = $task['task_data'];

        appLogger("[AIAgent] Executing task {$taskId} ({$taskType})");

        // Mark as running
        $this->updateStatus($taskId, self::STATUS_RUNNING);

        try {
            $result = $this->executeTaskByType($taskType, $taskData);

            if ($result['success']) {
                $this->completeTask($taskId, $result);
                appLogger("[AIAgent] Task {$taskId} completed successfully");
            } else {
                $this->failTask($taskId, $result['error'] ?? __('خطای ناشناخته', 'forooshyar'), $result);
                appLogger("[AIAgent] Task {$taskId} failed: " . ($result['error'] ?? 'Unknown'));
            }

            return $result;

        } catch (\Exception $e) {
            $this->failTask($taskId, $e->getMessage());
            appLogger("[AIAgent] Task {$taskId} exception: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute task based on type
     *
     * @param string $type
     * @param array $data
     * @return array
     */
    private function executeTaskByType($type, array $data)
    {
        switch ($type) {
            case self::TASK_PRICE_CHANGE:
                return $this->executePriceChange($data);

            case self::TASK_FOLLOWUP:
                return $this->executeFollowup($data);

            case self::TASK_CAMPAIGN:
                return $this->executeCampaign($data);

            case self::TASK_INVENTORY_CHECK:
                return $this->executeInventoryCheck($data);

            case self::TASK_ANALYSIS_JOB:
                // Analysis jobs are handled by AnalysisJobManager
                return ['success' => true, 'message' => 'Handled by AnalysisJobManager'];

            default:
                return [
                    'success' => false,
                    'error' => sprintf(__('نوع وظیفه ناشناخته: %s', 'forooshyar'), $type),
                ];
        }
    }

    /**
     * Execute price change task
     *
     * @param array $data
     * @return array
     */
    private function executePriceChange(array $data)
    {
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $newPrice = isset($data['new_price']) ? (float) $data['new_price'] : 0;

        if (!$productId || $newPrice <= 0) {
            return [
                'success' => false,
                'error' => __('اطلاعات محصول یا قیمت نامعتبر است', 'forooshyar'),
            ];
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return [
                'success' => false,
                'error' => __('محصول یافت نشد', 'forooshyar'),
            ];
        }

        $oldPrice = $product->get_regular_price();
        $product->set_regular_price($newPrice);
        
        // If product is on sale and new price is lower than sale price, update sale price too
        $salePrice = $product->get_sale_price();
        if ($salePrice && $newPrice < $salePrice) {
            $product->set_sale_price('');
        }

        $product->save();

        return [
            'success' => true,
            'message' => sprintf(
                __('قیمت محصول %s از %s به %s تغییر کرد', 'forooshyar'),
                $product->get_name(),
                wc_price($oldPrice),
                wc_price($newPrice)
            ),
            'data' => [
                'product_id' => $productId,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
            ],
        ];
    }

    /**
     * Execute followup task
     *
     * @param array $data
     * @return array
     */
    private function executeFollowup(array $data)
    {
        $followupType = isset($data['followup_type']) ? $data['followup_type'] : 'email';
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $message = isset($data['message']) ? $data['message'] : '';

        if (empty($message)) {
            return [
                'success' => false,
                'error' => __('پیام پیگیری خالی است', 'forooshyar'),
            ];
        }

        // Get customer info
        $customer = null;
        $email = '';
        $phone = '';

        if ($customerId) {
            $customer = new \WC_Customer($customerId);
            $email = $customer->get_email();
            $phone = $customer->get_billing_phone();
        }

        if ($followupType === 'email') {
            if (empty($email)) {
                return [
                    'success' => false,
                    'error' => __('ایمیل مشتری یافت نشد', 'forooshyar'),
                ];
            }

            return $this->actionExecutor->execute('send_email', [
                'email' => $email,
                'subject' => __('پیگیری از فروشگاه', 'forooshyar'),
                'message' => $message,
                'customer_id' => $customerId,
            ]);
        } else {
            if (empty($phone)) {
                return [
                    'success' => false,
                    'error' => __('شماره تلفن مشتری یافت نشد', 'forooshyar'),
                ];
            }

            return $this->actionExecutor->execute('send_sms', [
                'phone' => $phone,
                'message' => $message,
                'customer_id' => $customerId,
            ]);
        }
    }

    /**
     * Execute campaign task
     *
     * @param array $data
     * @return array
     */
    private function executeCampaign(array $data)
    {
        return $this->actionExecutor->execute('create_campaign', $data);
    }

    /**
     * Execute inventory check task
     *
     * @param array $data
     * @return array
     */
    private function executeInventoryCheck(array $data)
    {
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $threshold = isset($data['threshold']) ? (int) $data['threshold'] : 5;

        if (!$productId) {
            return [
                'success' => false,
                'error' => __('شناسه محصول نامعتبر است', 'forooshyar'),
            ];
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return [
                'success' => false,
                'error' => __('محصول یافت نشد', 'forooshyar'),
            ];
        }

        $stock = $product->get_stock_quantity();

        if ($stock !== null && $stock <= $threshold) {
            return $this->actionExecutor->execute('inventory_alert', [
                'product_id' => $productId,
                'current_stock' => $stock,
                'threshold' => $threshold,
                'alert_message' => sprintf(
                    __('موجودی محصول "%s" به %d رسیده است', 'forooshyar'),
                    $product->get_name(),
                    $stock
                ),
            ]);
        }

        return [
            'success' => true,
            'message' => __('موجودی در حد مجاز است', 'forooshyar'),
            'data' => [
                'product_id' => $productId,
                'current_stock' => $stock,
                'threshold' => $threshold,
            ],
        ];
    }

    /**
     * Update task status
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status)
    {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->table,
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Complete task
     *
     * @param int $id
     * @param array $result
     * @return bool
     */
    public function completeTask($id, array $result = [])
    {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_COMPLETED,
                'executed_at' => current_time('mysql'),
                'result' => wp_json_encode($result),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Fail task
     *
     * @param int $id
     * @param string $error
     * @param array $result
     * @return bool
     */
    public function failTask($id, $error, array $result = [])
    {
        global $wpdb;

        $result['error'] = $error;

        return (bool) $wpdb->update(
            $this->table,
            [
                'status' => self::STATUS_FAILED,
                'executed_at' => current_time('mysql'),
                'result' => wp_json_encode($result),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Cancel task
     *
     * @param int $id
     * @return bool
     */
    public function cancelTask($id)
    {
        $task = $this->getTask($id);
        
        if (!$task || $task['status'] !== self::STATUS_PENDING) {
            return false;
        }

        return $this->updateStatus($id, self::STATUS_CANCELLED);
    }

    /**
     * Get scheduled tasks for a product
     *
     * @param int $productId
     * @return array
     */
    public function getTasksForProduct($productId)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = %s 
             AND task_data LIKE %s
             ORDER BY scheduled_at ASC",
            self::STATUS_PENDING,
            '%"product_id":' . $productId . '%'
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['task_data'] = json_decode($row['task_data'], true);
        }

        return $rows;
    }

    /**
     * Get scheduled tasks for a customer
     *
     * @param int $customerId
     * @return array
     */
    public function getTasksForCustomer($customerId)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = %s 
             AND task_data LIKE %s
             ORDER BY scheduled_at ASC",
            self::STATUS_PENDING,
            '%"customer_id":' . $customerId . '%'
        ), ARRAY_A);

        foreach ($rows as &$row) {
            $row['task_data'] = json_decode($row['task_data'], true);
        }

        return $rows;
    }

    /**
     * Get task statistics
     *
     * @param int $days
     * @return array
     */
    public function getStatistics($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        // Count by status
        $byStatus = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
             FROM {$this->table} 
             WHERE created_at >= %s 
             GROUP BY status",
            $date
        ), ARRAY_A);

        // Count by type
        $byType = $wpdb->get_results($wpdb->prepare(
            "SELECT task_type, COUNT(*) as count 
             FROM {$this->table} 
             WHERE created_at >= %s 
             GROUP BY task_type",
            $date
        ), ARRAY_A);

        // Pending count
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
            self::STATUS_PENDING
        ));

        // Due now count
        $dueNow = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE status = %s AND scheduled_at <= %s",
            self::STATUS_PENDING,
            current_time('mysql')
        ));

        return [
            'by_status' => $byStatus,
            'by_type' => $byType,
            'pending' => (int) $pending,
            'due_now' => (int) $dueNow,
        ];
    }

    /**
     * Cleanup old completed/failed tasks
     *
     * @param int $days
     * @return int Number of deleted tasks
     */
    public function cleanup($days = 90)
    {
        global $wpdb;
        $date = date('Y-m-d', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} 
             WHERE status IN (%s, %s, %s) 
             AND created_at < %s",
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            $date
        ));

        appLogger("[AIAgent] Cleaned up {$deleted} old scheduled tasks");

        return (int) $deleted;
    }
}
