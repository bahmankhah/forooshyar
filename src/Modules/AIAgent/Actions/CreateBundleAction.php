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
    protected $requiredFields = [];
    protected $optionalFields = ['product_ids', 'bundle_name', 'bundle_discount', 'bundle_description', 'discount_amount', 'description', 'expiry_date', 'entity_id', 'entity_type'];

    public function execute(array $data)
    {
        // Support LLM field names
        $bundleName = $this->getField($data, 'bundle_name', __('بسته جدید', 'forooshyar'));
        $bundleDiscount = $this->getField($data, 'bundle_discount', $this->getField($data, 'discount_amount', 10));
        $bundleDescription = $this->getField($data, 'bundle_description', $this->getField($data, 'description', ''));
        
        // Get product IDs - if entity_type is product, include that product
        $productIds = $this->getField($data, 'product_ids', []);
        if (!is_array($productIds)) {
            $productIds = [];
        }
        
        $entityType = $this->getField($data, 'entity_type');
        $entityId = $this->getField($data, 'entity_id');
        if ($entityType === 'product' && $entityId && !in_array($entityId, $productIds)) {
            $productIds[] = $entityId;
        }
        
        // Store bundle suggestion for manual creation
        $bundleData = [
            'name' => sanitize_text_field($bundleName),
            'products' => array_map('absint', $productIds),
            'discount' => floatval($bundleDiscount),
            'description' => sanitize_textarea_field($bundleDescription),
            'created_at' => current_time('mysql'),
        ];

        $bundleId = 'bundle_suggestion_' . time();
        update_option('aiagent_' . $bundleId, $bundleData);

        return $this->success(__('پیشنهاد بسته ایجاد شد', 'forooshyar'), array_merge($bundleData, ['id' => $bundleId]));
    }
    
    public function validate(array $data)
    {
        $errors = [];
        
        $bundleName = $this->getField($data, 'bundle_name', '');
        if (empty($bundleName)) {
            $errors[] = __('نام بسته الزامی است', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
