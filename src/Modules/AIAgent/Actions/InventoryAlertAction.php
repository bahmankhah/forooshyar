<?php
/**
 * Inventory Alert Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class InventoryAlertAction extends AbstractAction
{
    protected $type = 'inventory_alert';
    protected $name = 'Inventory Alert';
    protected $description = 'Set low stock alert for product';
    protected $requiredFields = [];
    protected $optionalFields = ['product_id', 'entity_id', 'entity_type', 'threshold', 'recommended_quantity', 'alert_message', 'alert_email'];

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
        
        $productId = absint($productId);
        $product = wc_get_product($productId);

        if (!$product) {
            return $this->error(__('محصول یافت نشد', 'forooshyar'));
        }
        
        // Support both threshold and recommended_quantity
        $threshold = $this->getField($data, 'threshold', $this->getField($data, 'recommended_quantity', 10));
        $threshold = absint($threshold);

        $product->set_low_stock_amount($threshold);
        $product->save();
        
        // Store alert message if provided
        $alertMessage = $this->getField($data, 'alert_message', '');
        if ($alertMessage) {
            update_post_meta($productId, '_aiagent_inventory_alert_message', sanitize_text_field($alertMessage));
        }

        return $this->success(__('هشدار موجودی تنظیم شد', 'forooshyar'), [
            'product_id' => $productId,
            'threshold' => $threshold,
            'alert_message' => $alertMessage,
        ]);
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
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
