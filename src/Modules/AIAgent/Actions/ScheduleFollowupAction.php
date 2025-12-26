<?php
/**
 * Schedule Followup Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class ScheduleFollowupAction extends AbstractAction
{
    protected $type = 'schedule_followup';
    protected $name = 'Schedule Followup';
    protected $description = 'Schedule a future action or reminder';
    protected $requiredFields = [];
    protected $optionalFields = ['followup_days', 'followup_message', 'followup_type', 'customer_id', 'entity_id', 'entity_type', 'schedule_time', 'action', 'message'];

    public function execute(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        
        // Support LLM field names
        $followupDays = intval($this->getField($data, 'followup_days', 7));
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
        
        $taskData = [
            'followup_type' => $followupType,
            'followup_message' => $followupMessage,
            'customer_id' => $customerId,
            'entity_id' => $this->getField($data, 'entity_id'),
            'entity_type' => $this->getField($data, 'entity_type'),
        ];

        $result = $wpdb->insert($table, [
            'task_type' => 'followup',
            'task_data' => wp_json_encode($taskData),
            'scheduled_at' => $scheduleTime,
            'status' => 'pending',
        ], ['%s', '%s', '%s', '%s']);

        if ($result) {
            return $this->success(__('پیگیری زمان‌بندی شد', 'forooshyar'), [
                'id' => $wpdb->insert_id,
                'scheduled_at' => $scheduleTime,
                'followup_type' => $followupType,
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
