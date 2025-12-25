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
    protected $requiredFields = ['schedule_time', 'action'];
    protected $optionalFields = ['customer_id', 'product_id', 'message'];

    public function execute(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';

        $result = $wpdb->insert($table, [
            'task_type' => 'followup',
            'task_data' => wp_json_encode($data),
            'scheduled_at' => $data['schedule_time'],
            'status' => 'pending',
        ], ['%s', '%s', '%s', '%s']);

        if ($result) {
            return $this->success('Followup scheduled', ['id' => $wpdb->insert_id]);
        }
        return $this->error('Failed to schedule followup');
    }
}
