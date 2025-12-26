<?php
/**
 * Update Product Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class UpdateProductAction extends AbstractAction
{
    /** @var string */
    protected $type = 'update_product';

    /** @var string */
    protected $name = 'Update Product';

    /** @var string */
    protected $description = 'Update WooCommerce product data';

    /** @var array */
    protected $requiredFields = [];

    /** @var array */
    protected $optionalFields = ['product_id', 'entity_id', 'entity_type', 'new_price', 'price', 'price_change_percent', 'sale_price', 'stock_quantity', 'featured', 'status', 'update_description'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
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

        $updated = [];

        // Update regular price - support both new_price and price
        $newPrice = $this->getField($data, 'new_price', $this->getField($data, 'price'));
        if ($newPrice !== null) {
            $price = floatval($newPrice);
            $product->set_regular_price($price);
            $updated['price'] = $price;
        }

        // Update sale price
        $salePrice = $this->getField($data, 'sale_price');
        if ($salePrice !== null) {
            $salePrice = $salePrice === '' ? '' : floatval($salePrice);
            $product->set_sale_price($salePrice);
            $updated['sale_price'] = $salePrice;
        }

        // Update stock quantity
        $stockQty = $this->getField($data, 'stock_quantity');
        if ($stockQty !== null) {
            $stockQty = intval($stockQty);
            $product->set_stock_quantity($stockQty);
            $product->set_manage_stock(true);
            $updated['stock_quantity'] = $stockQty;
        }

        // Update featured status
        $featured = $this->getField($data, 'featured');
        if ($featured !== null) {
            $featured = (bool) $featured;
            $product->set_featured($featured);
            $updated['featured'] = $featured;
        }

        // Update status
        $status = $this->getField($data, 'status');
        if ($status !== null) {
            $validStatuses = ['publish', 'draft', 'pending', 'private'];
            if (in_array($status, $validStatuses)) {
                $product->set_status($status);
                $updated['status'] = $status;
            }
        }

        if (empty($updated)) {
            return $this->error(__('هیچ فیلدی برای بروزرسانی مشخص نشده', 'forooshyar'));
        }

        try {
            $product->save();

            return $this->success(__('محصول با موفقیت بروزرسانی شد', 'forooshyar'), [
                'product_id' => $productId,
                'updated_fields' => $updated,
            ]);
        } catch (\Exception $e) {
            return $this->error(__('خطا در بروزرسانی محصول: ', 'forooshyar') . $e->getMessage());
        }
    }

    /**
     * Validate action data
     *
     * @param array $data
     * @return array
     */
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
        } else {
            // Validate product exists
            $product = wc_get_product(absint($productId));
            if (!$product) {
                $errors[] = __('محصول یافت نشد', 'forooshyar');
            }
        }

        // Validate price values
        $newPrice = $this->getField($data, 'new_price', $this->getField($data, 'price'));
        if ($newPrice !== null && floatval($newPrice) < 0) {
            $errors[] = __('قیمت نمی‌تواند منفی باشد', 'forooshyar');
        }

        $salePrice = $this->getField($data, 'sale_price');
        if ($salePrice !== null && $salePrice !== '' && floatval($salePrice) < 0) {
            $errors[] = __('قیمت حراج نمی‌تواند منفی باشد', 'forooshyar');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
