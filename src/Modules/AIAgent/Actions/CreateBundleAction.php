<?php
/**
 * Create Bundle Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class CreateBundleAction extends AbstractAction
{
    protected $type = 'create_bundle';
    protected $name = 'Create Bundle';
    protected $description = 'Create product bundle with discount';
    protected $requiredFields = ['product_ids', 'bundle_name', 'discount_amount'];
    protected $optionalFields = ['description', 'expiry_date'];

    public function execute(array $data)
    {
        // Store bundle suggestion for manual creation
        $bundleData = [
            'name' => sanitize_text_field($data['bundle_name']),
            'products' => array_map('absint', $data['product_ids']),
            'discount' => floatval($data['discount_amount']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'created_at' => current_time('mysql'),
        ];

        update_option('aiagent_bundle_suggestion_' . time(), $bundleData);

        return $this->success('Bundle suggestion created', $bundleData);
    }
}
