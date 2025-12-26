<?php
/**
 * Schedule Price Change Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class SchedulePriceChangeAction extends AbstractAction
{
    protected $type = 'schedule_price_change';
    protected $name = 'Schedule Price Change';
    protected $description = 'Schedule future price change for product';
    protected $requiredFields = [];
    protected $optionalFields = ['product_id', 'entity_id', 'entity_type', 'new_price', 'schedule_time', 'change_date', 'change_reason', 'revert_time', 'reason'];

    public function execute(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';

        // Support both product_id and entity_id
        $productId = $this->getField($data, 'product_id');
        if (!$productId) {
            $entityType = $this->getField($data, 'entity_type');
            $entityId = $this->getField($data, 'entity_id');
            if ($entityType === 'product' && $entityId) {
                $productId = $entityId;
            }
        }
        
        if (!$productId) {
            return $this->error(__('شناسه محصول یافت نشد', 'forooshyar'));
        }
        
        $productId = absint($productId);
        $product = wc_get_product($productId);

        if (!$product) {
            return $this->error(__('محصول یافت نشد', 'forooshyar'));
        }
        
        $newPrice = $this->getField($data, 'new_price');
        if (!$newPrice) {
            return $this->error(__('قیمت جدید الزامی است', 'forooshyar'));
        }
        
        // Support both schedule_time and change_date
        $scheduleTime = $this->getField($data, 'schedule_time', $this->getField($data, 'change_date'));
        if (!$scheduleTime) {
            // Default to tomorrow
            $scheduleTime = date('Y-m-d H:i:s', strtotime('+1 day'));
        }
        
        $reason = $this->getField($data, 'change_reason', $this->getField($data, 'reason', ''));

        $taskData = [
            'product_id' => $productId,
            'current_price' => $product->get_regular_price(),
            'new_price' => floatval($newPrice),
            'revert_time' => $this->getField($data, 'revert_time'),
            'reason' => sanitize_text_field($reason),
        ];

        $result = $wpdb->insert($table, [
            'task_type' => 'price_change',
            'task_data' => wp_json_encode($taskData),
            'scheduled_at' => $scheduleTime,
            'status' => 'pending',
        ], ['%s', '%s', '%s', '%s']);

        if ($result) {
            return $this->success(__('تغییر قیمت زمان‌بندی شد', 'forooshyar'), [
                'id' => $wpdb->insert_id,
                'product_id' => $productId,
                'new_price' => floatval($newPrice),
                'scheduled_at' => $scheduleTime,
            ]);
        }

        return $this->error(__('خطا در زمان‌بندی تغییر قیمت', 'forooshyar'));
    }
    
    public function validate(array $data)
    {
        $errors = [];
        
        // Check for product_id or entity_id
        $productId = $this->getField($data, 'product_id');
        if (!$productId) {
            $entityType = $this->getField($data, 'entity_type');
            $entityId = $this->getField($data, 'entity_id');
            if ($entityType === 'product' && $entityId) {
                $productId = $entityId;
            }
        }
        
        if (!$productId) {
            $errors[] = __('شناسه محصول الزامی است', 'forooshyar');
        }
        
        $newPrice = $this->getField($data, 'new_price');
        if (!$newPrice || floatval($newPrice) <= 0) {
            $errors[] = __('قیمت جدید الزامی است و باید بیشتر از صفر باشد', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
