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
    protected $requiredFields = ['product_id', 'new_price', 'schedule_time'];
    protected $optionalFields = ['revert_time', 'reason'];

    public function execute(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';

        $productId = absint($data['product_id']);
        $product = wc_get_product($productId);

        if (!$product) {
            return $this->error('Product not found');
        }

        $taskData = [
            'product_id' => $productId,
            'current_price' => $product->get_regular_price(),
            'new_price' => floatval($data['new_price']),
            'revert_time' => isset($data['revert_time']) ? $data['revert_time'] : null,
            'reason' => isset($data['reason']) ? sanitize_text_field($data['reason']) : '',
        ];

        $result = $wpdb->insert($table, [
            'task_type' => 'price_change',
            'task_data' => wp_json_encode($taskData),
            'scheduled_at' => $data['schedule_time'],
            'status' => 'pending',
        ], ['%s', '%s', '%s', '%s']);

        if ($result) {
            return $this->success('Price change scheduled', [
                'id' => $wpdb->insert_id,
                'product_id' => $productId,
                'scheduled_at' => $data['schedule_time'],
            ]);
        }

        return $this->error('Failed to schedule price change');
    }
}
