<?php
/**
 * Schedule Followup Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

use Forooshyar\WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\ScheduledTaskService;

class ScheduleFollowupAction extends AbstractAction
{
    protected $type = 'schedule_followup';
    protected $name = 'Schedule Followup';
    protected $description = 'Schedule a future action or reminder';
    protected $requiredFields = [];
    protected $optionalFields = ['followup_days', 'followup_message', 'followup_type', 'customer_id', 'product_id', 'entity_id', 'entity_type', 'schedule_time', 'action', 'message'];

    public function execute(array $data)
    {
        // Support LLM field names
        $followupDays = \intval($this->getField($data, 'followup_days', 7));
        $followupMessage = $this->getField($data, 'followup_message', $this->getField($data, 'message', ''));
        $followupType = $this->getField($data, 'followup_type', 'email');
        
        // Calculate schedule time from followup_days
        $scheduleTime = $this->getField($data, 'schedule_time');
        if (!$scheduleTime && $followupDays > 0) {
            $scheduleTime = date('Y-m-d H:i:s', strtotime("+{$followupDays} days"));
        }
        
        // Get customer/entity info
        $customerId = $this->getField($data, 'customer_id');
        if (!$customerId) {
            $entityType = $this->getField($data, 'entity_type');
            $entityId = $this->getField($data, 'entity_id');
            if ($entityType === 'customer' && $entityId) {
                $customerId = $entityId;
            }
        }
        
        // Get product ID if available
        $productId = $this->getField($data, 'product_id');
        if (!$productId) {
            $entityType = $this->getField($data, 'entity_type');
            $entityId = $this->getField($data, 'entity_id');
            if ($entityType === 'product' && $entityId) {
                $productId = $entityId;
            }
        }

        // Use ScheduledTaskService
        $scheduledTaskService = Container::resolve(ScheduledTaskService::class);
        
        $taskId = $scheduledTaskService->scheduleFollowup(
            $followupType,
            $customerId,
            $productId,
            $followupMessage,
            $scheduleTime
        );

        if ($taskId) {
            return $this->success(__('پیگیری زمان‌بندی شد', 'forooshyar'), [
                'task_id' => $taskId,
                'scheduled_at' => $scheduleTime,
                'followup_type' => $followupType,
                'customer_id' => $customerId,
                'product_id' => $productId,
            ]);
        }
        
        return $this->error(__('خطا در زمان‌بندی پیگیری', 'forooshyar'));
    }
    
    public function validate(array $data)
    {
        $errors = [];
        
        // Check for followup_days or schedule_time
        $followupDays = $this->getField($data, 'followup_days', 0);
        $scheduleTime = $this->getField($data, 'schedule_time', '');
        
        if ($followupDays <= 0 && empty($scheduleTime)) {
            $errors[] = __('تعداد روز پیگیری یا زمان زمان‌بندی الزامی است', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
