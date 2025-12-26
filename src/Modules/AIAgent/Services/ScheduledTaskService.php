<?php
/**
 * Scheduled Task Service
 * 
 * مدیریت وظایف زمان‌بندی شده با استفاده از WooCommerce Action Scheduler
 * این سرویس از Action Scheduler برای زمان‌بندی تغییرات قیمت، پیگیری‌ها و کمپین‌ها استفاده می‌کند
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appLogger;

class ScheduledTaskService
{
    // Action Scheduler hooks
    const HOOK_PRICE_CHANGE = 'aiagent_scheduled_price_change';
    const HOOK_FOLLOWUP = 'aiagent_scheduled_followup';
    const HOOK_CAMPAIGN = 'aiagent_scheduled_campaign';
    const HOOK_INVENTORY_CHECK = 'aiagent_scheduled_inventory_check';
    
    // Group name for Action Scheduler
    const AS_GROUP = 'aiagent_scheduled_tasks';

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
        $this->actionExecutor = $actionExecutor;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Register Action Scheduler hooks
     * Called from AIAgentModule::boot()
     *
     * @return void
     */
    public function register()
    {
        // Register hooks for scheduled tasks
        add_action(self::HOOK_PRICE_CHANGE, [$this, 'executePriceChange'], 10, 1);
        add_action(self::HOOK_FOLLOWUP, [$this, 'executeFollowup'], 10, 1);
        add_action(self::HOOK_CAMPAIGN, [$this, 'executeCampaign'], 10, 1);
        add_action(self::HOOK_INVENTORY_CHECK, [$this, 'executeInventoryCheck'], 10, 1);
    }

    /**
     * Check if Action Scheduler is available
     *
     * @return bool
     */
    public function isActionSchedulerAvailable()
    {
        return function_exists('as_schedule_single_action') && 
               function_exists('as_unschedule_all_actions');
    }

    /**
     * Schedule a price change
     *
     * @param int $productId
     * @param float $newPrice
     * @param string $scheduledAt DateTime string (Y-m-d H:i:s)
     * @param float|null $revertPrice Price to revert to (optional)
     * @param string|null $revertAt DateTime to revert (optional)
     * @param string|null $reason
     * @return int|false Action ID or false
     */
    public function schedulePriceChange($productId, $newPrice, $scheduledAt, $revertPrice = null, $revertAt = null, $reason = null)
    {
        if (!$this->isActionSchedulerAvailable()) {
            appLogger("[AIAgent] Action Scheduler not available for price change");
            return false;
        }

        $data = [
            'product_id' => $productId,
            'new_price' => $newPrice,
            'reason' => $reason,
        ];

        $timestamp = strtotime($scheduledAt);
        if ($timestamp === false) {
            $timestamp = time() + 86400; // Default to tomorrow
        }

        $actionId = as_schedule_single_action(
            $timestamp,
            self::HOOK_PRICE_CHANGE,
            [$data],
            self::AS_GROUP
        );

        appLogger("[AIAgent] Price change scheduled: Product #{$productId} to {$newPrice} at {$scheduledAt} (Action ID: {$actionId})");

        // Schedule revert if specified
        if ($actionId && $revertPrice !== null && $revertAt !== null) {
            $revertData = [
                'product_id' => $productId,
                'new_price' => $revertPrice,
                'reason' => __('بازگشت قیمت به حالت قبل', 'forooshyar'),
                'original_action_id' => $actionId,
            ];
            
            $revertTimestamp = strtotime($revertAt);
            if ($revertTimestamp !== false) {
                as_schedule_single_action(
                    $revertTimestamp,
                    self::HOOK_PRICE_CHANGE,
                    [$revertData],
                    self::AS_GROUP
                );
                appLogger("[AIAgent] Price revert scheduled: Product #{$productId} to {$revertPrice} at {$revertAt}");
            }
        }

        return $actionId;
    }

    /**
     * Schedule a followup action
     *
     * @param string $followupType 'email' or 'sms'
     * @param int|null $customerId
     * @param int|null $productId
     * @param string $message
     * @param string $scheduledAt
     * @return int|false Action ID or false
     */
    public function scheduleFollowup($followupType, $customerId, $productId, $message, $scheduledAt)
    {
        if (!$this->isActionSchedulerAvailable()) {
            appLogger("[AIAgent] Action Scheduler not available for followup");
            return false;
        }

        $data = [
            'followup_type' => $followupType,
            'customer_id' => $customerId,
            'product_id' => $productId,
            'message' => $message,
        ];

        $timestamp = strtotime($scheduledAt);
        if ($timestamp === false) {
            $timestamp = time() + (7 * 86400); // Default to 7 days
        }

        $actionId = as_schedule_single_action(
            $timestamp,
            self::HOOK_FOLLOWUP,
            [$data],
            self::AS_GROUP
        );

        appLogger("[AIAgent] Followup scheduled: {$followupType} for customer #{$customerId} at {$scheduledAt} (Action ID: {$actionId})");

        return $actionId;
    }

    /**
     * Schedule a campaign
     *
     * @param string $campaignName
     * @param string $campaignMessage
     * @param string $targetAudience
     * @param string $scheduledAt
     * @param int $durationDays
     * @return int|false Action ID or false
     */
    public function scheduleCampaign($campaignName, $campaignMessage, $targetAudience, $scheduledAt, $durationDays = 7)
    {
        if (!$this->isActionSchedulerAvailable()) {
            appLogger("[AIAgent] Action Scheduler not available for campaign");
            return false;
        }

        $data = [
            'campaign_name' => $campaignName,
            'campaign_message' => $campaignMessage,
            'target_audience' => $targetAudience,
            'duration_days' => $durationDays,
        ];

        $timestamp = strtotime($scheduledAt);
        if ($timestamp === false) {
            $timestamp = time();
        }

        $actionId = as_schedule_single_action(
            $timestamp,
            self::HOOK_CAMPAIGN,
            [$data],
            self::AS_GROUP
        );

        appLogger("[AIAgent] Campaign scheduled: {$campaignName} at {$scheduledAt} (Action ID: {$actionId})");

        return $actionId;
    }

    /**
     * Schedule an inventory check
     *
     * @param int $productId
     * @param int $threshold
     * @param string $scheduledAt
     * @return int|false Action ID or false
     */
    public function scheduleInventoryCheck($productId, $threshold, $scheduledAt)
    {
        if (!$this->isActionSchedulerAvailable()) {
            return false;
        }

        $data = [
            'product_id' => $productId,
            'threshold' => $threshold,
        ];

        $timestamp = strtotime($scheduledAt);
        if ($timestamp === false) {
            $timestamp = time();
        }

        return as_schedule_single_action(
            $timestamp,
            self::HOOK_INVENTORY_CHECK,
            [$data],
            self::AS_GROUP
        );
    }

    /**
     * Execute price change (called by Action Scheduler)
     *
     * @param array $data
     * @return void
     */
    public function executePriceChange(array $data)
    {
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $newPrice = isset($data['new_price']) ? (float) $data['new_price'] : 0;

        appLogger("[AIAgent] Executing scheduled price change: Product #{$productId} to {$newPrice}");

        if (!$productId || $newPrice <= 0) {
            appLogger("[AIAgent] Invalid price change data");
            return;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            appLogger("[AIAgent] Product not found: #{$productId}");
            return;
        }

        $oldPrice = $product->get_regular_price();
        $product->set_regular_price($newPrice);
        
        // If product is on sale and new price is lower than sale price, clear sale price
        $salePrice = $product->get_sale_price();
        if ($salePrice && $newPrice < $salePrice) {
            $product->set_sale_price('');
        }

        $product->save();

        appLogger("[AIAgent] Price changed: Product #{$productId} from {$oldPrice} to {$newPrice}");
    }

    /**
     * Execute followup (called by Action Scheduler)
     *
     * @param array $data
     * @return void
     */
    public function executeFollowup(array $data)
    {
        $followupType = isset($data['followup_type']) ? $data['followup_type'] : 'email';
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $message = isset($data['message']) ? $data['message'] : '';

        appLogger("[AIAgent] Executing scheduled followup: {$followupType} for customer #{$customerId}");

        if (empty($message)) {
            appLogger("[AIAgent] Followup message is empty");
            return;
        }

        // Get customer info
        $email = '';
        $phone = '';

        if ($customerId) {
            $customer = new \WC_Customer($customerId);
            $email = $customer->get_email();
            $phone = $customer->get_billing_phone();
        }

        if ($followupType === 'email') {
            if (empty($email)) {
                appLogger("[AIAgent] Customer email not found");
                return;
            }

            $result = $this->actionExecutor->execute('send_email', [
                'email' => $email,
                'subject' => __('پیگیری از فروشگاه', 'forooshyar'),
                'message' => $message,
                'customer_id' => $customerId,
            ]);
        } else {
            if (empty($phone)) {
                appLogger("[AIAgent] Customer phone not found");
                return;
            }

            $result = $this->actionExecutor->execute('send_sms', [
                'phone' => $phone,
                'message' => $message,
                'customer_id' => $customerId,
            ]);
        }

        appLogger("[AIAgent] Followup executed: " . ($result['success'] ? 'success' : 'failed'));
    }

    /**
     * Execute campaign (called by Action Scheduler)
     *
     * @param array $data
     * @return void
     */
    public function executeCampaign(array $data)
    {
        appLogger("[AIAgent] Executing scheduled campaign: " . ($data['campaign_name'] ?? 'Unknown'));
        
        $result = $this->actionExecutor->execute('create_campaign', $data);
        
        appLogger("[AIAgent] Campaign executed: " . ($result['success'] ? 'success' : 'failed'));
    }

    /**
     * Execute inventory check (called by Action Scheduler)
     *
     * @param array $data
     * @return void
     */
    public function executeInventoryCheck(array $data)
    {
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $threshold = isset($data['threshold']) ? (int) $data['threshold'] : 5;

        appLogger("[AIAgent] Executing scheduled inventory check: Product #{$productId}");

        if (!$productId) {
            return;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return;
        }

        $stock = $product->get_stock_quantity();

        if ($stock !== null && $stock <= $threshold) {
            $this->actionExecutor->execute('inventory_alert', [
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
    }

    /**
     * Cancel all scheduled tasks for a product
     *
     * @param int $productId
     * @return void
     */
    public function cancelTasksForProduct($productId)
    {
        if (!$this->isActionSchedulerAvailable()) {
            return;
        }

        // Get all pending actions for this product
        $actions = as_get_scheduled_actions([
            'hook' => self::HOOK_PRICE_CHANGE,
            'group' => self::AS_GROUP,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
        ]);

        foreach ($actions as $actionId => $action) {
            $args = $action->get_args();
            if (isset($args[0]['product_id']) && $args[0]['product_id'] == $productId) {
                as_unschedule_action(self::HOOK_PRICE_CHANGE, $args, self::AS_GROUP);
            }
        }

        appLogger("[AIAgent] Cancelled scheduled tasks for product #{$productId}");
    }

    /**
     * Cancel all scheduled tasks for a customer
     *
     * @param int $customerId
     * @return void
     */
    public function cancelTasksForCustomer($customerId)
    {
        if (!$this->isActionSchedulerAvailable()) {
            return;
        }

        $actions = as_get_scheduled_actions([
            'hook' => self::HOOK_FOLLOWUP,
            'group' => self::AS_GROUP,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
        ]);

        foreach ($actions as $actionId => $action) {
            $args = $action->get_args();
            if (isset($args[0]['customer_id']) && $args[0]['customer_id'] == $customerId) {
                as_unschedule_action(self::HOOK_FOLLOWUP, $args, self::AS_GROUP);
            }
        }

        appLogger("[AIAgent] Cancelled scheduled tasks for customer #{$customerId}");
    }

    /**
     * Get statistics about scheduled tasks
     *
     * @return array
     */
    public function getStatistics()
    {
        if (!$this->isActionSchedulerAvailable()) {
            return [
                'pending' => 0,
                'by_type' => [],
            ];
        }

        $hooks = [
            self::HOOK_PRICE_CHANGE => 'price_change',
            self::HOOK_FOLLOWUP => 'followup',
            self::HOOK_CAMPAIGN => 'campaign',
            self::HOOK_INVENTORY_CHECK => 'inventory_check',
        ];

        $byType = [];
        $totalPending = 0;

        foreach ($hooks as $hook => $type) {
            $count = as_get_scheduled_actions([
                'hook' => $hook,
                'group' => self::AS_GROUP,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
            ], 'ids');
            
            $count = is_array($count) ? count($count) : 0;
            $byType[$type] = $count;
            $totalPending += $count;
        }

        return [
            'pending' => $totalPending,
            'by_type' => $byType,
        ];
    }

    /**
     * Get pending tasks (for display in admin)
     *
     * @param int $limit
     * @return array
     */
    public function getPendingTasks($limit = 50)
    {
        if (!$this->isActionSchedulerAvailable()) {
            return [];
        }

        $tasks = [];
        $hooks = [
            self::HOOK_PRICE_CHANGE => 'price_change',
            self::HOOK_FOLLOWUP => 'followup',
            self::HOOK_CAMPAIGN => 'campaign',
            self::HOOK_INVENTORY_CHECK => 'inventory_check',
        ];

        foreach ($hooks as $hook => $type) {
            $actions = as_get_scheduled_actions([
                'hook' => $hook,
                'group' => self::AS_GROUP,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => $limit,
                'orderby' => 'date',
                'order' => 'ASC',
            ]);

            foreach ($actions as $actionId => $action) {
                $args = $action->get_args();
                $tasks[] = [
                    'id' => $actionId,
                    'type' => $type,
                    'data' => isset($args[0]) ? $args[0] : [],
                    'scheduled_at' => $action->get_schedule()->get_date()->format('Y-m-d H:i:s'),
                ];
            }
        }

        // Sort by scheduled_at
        usort($tasks, function($a, $b) {
            return strtotime($a['scheduled_at']) - strtotime($b['scheduled_at']);
        });

        return array_slice($tasks, 0, $limit);
    }

    /**
     * Cancel a specific task by action ID
     *
     * @param int $actionId
     * @return bool
     */
    public function cancelTask($actionId)
    {
        if (!$this->isActionSchedulerAvailable()) {
            return false;
        }

        try {
            $store = \ActionScheduler_Store::instance();
            $store->cancel_action($actionId);
            appLogger("[AIAgent] Cancelled scheduled task: #{$actionId}");
            return true;
        } catch (\Exception $e) {
            appLogger("[AIAgent] Failed to cancel task #{$actionId}: " . $e->getMessage());
            return false;
        }
    }
}
