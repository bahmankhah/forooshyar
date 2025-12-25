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
    protected $requiredFields = ['product_id', 'threshold'];
    protected $optionalFields = ['alert_email'];

    public function execute(array $data)
    {
        $productId = absint($data['product_id']);
        $threshold = absint($data['threshold']);
        $product = wc_get_product($productId);

        if (!$product) {
            return $this->error('Product not found');
        }

        $product->set_low_stock_amount($threshold);
        $product->save();

        return $this->success('Inventory alert set', [
            'product_id' => $productId,
            'threshold' => $threshold,
        ]);
    }
}
