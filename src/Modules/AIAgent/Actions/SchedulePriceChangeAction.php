<?php
/**
 * Schedule Price Change Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

use Forooshyar\WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\ScheduledTaskService;

class SchedulePriceChangeAction extends AbstractAction
{
    protected $type = 'schedule_price_change';
    protected $name = 'Schedule Price Change';
    protected $description = 'Schedule future price change for product';
    protected $requiredFields = [];
    protected $optionalFields = ['product_id', 'entity_id', 'entity_type', 'new_price', 'schedule_time', 'change_date', 'change_reason', 'revert_time', 'revert_price', 'reason'];

    public function execute(array $data)
    {
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
        
        $productId = \absint($productId);
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
        
        // Get revert settings
        $revertPrice = $this->getField($data, 'revert_price');
        $revertTime = $this->getField($data, 'revert_time');
        
        // If no revert price specified but revert time is, use current price
        if ($revertTime && !$revertPrice) {
            $revertPrice = $product->get_regular_price();
        }

        // Use ScheduledTaskService
        $scheduledTaskService = Container::resolve(ScheduledTaskService::class);
        
        $taskId = $scheduledTaskService->schedulePriceChange(
            $productId,
            \floatval($newPrice),
            $scheduleTime,
            $revertPrice ? \floatval($revertPrice) : null,
            $revertTime,
            sanitize_text_field($reason)
        );

        if ($taskId) {
            $result = [
                'task_id' => $taskId,
                'product_id' => $productId,
                'product_name' => $product->get_name(),
                'current_price' => $product->get_regular_price(),
                'new_price' => \floatval($newPrice),
                'scheduled_at' => $scheduleTime,
            ];
            
            if ($revertTime) {
                $result['revert_price'] = $revertPrice;
                $result['revert_at'] = $revertTime;
            }
            
            return $this->success(__('تغییر قیمت زمان‌بندی شد', 'forooshyar'), $result);
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
        if (!$newPrice || \floatval($newPrice) <= 0) {
            $errors[] = __('قیمت جدید الزامی است و باید بیشتر از صفر باشد', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
