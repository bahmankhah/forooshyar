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
    protected $requiredFields = ['product_id'];

    /** @var array */
    protected $optionalFields = ['price', 'sale_price', 'stock_quantity', 'featured', 'status'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data)
    {
        $productId = absint($data['product_id']);
        $product = wc_get_product($productId);

        if (!$product) {
            return $this->error('Product not found');
        }

        $updated = [];

        // Update regular price
        if (isset($data['price'])) {
            $price = floatval($data['price']);
            $product->set_regular_price($price);
            $updated['price'] = $price;
        }

        // Update sale price
        if (isset($data['sale_price'])) {
            $salePrice = $data['sale_price'] === '' ? '' : floatval($data['sale_price']);
            $product->set_sale_price($salePrice);
            $updated['sale_price'] = $salePrice;
        }

        // Update stock quantity
        if (isset($data['stock_quantity'])) {
            $stockQty = intval($data['stock_quantity']);
            $product->set_stock_quantity($stockQty);
            $product->set_manage_stock(true);
            $updated['stock_quantity'] = $stockQty;
        }

        // Update featured status
        if (isset($data['featured'])) {
            $featured = (bool) $data['featured'];
            $product->set_featured($featured);
            $updated['featured'] = $featured;
        }

        // Update status
        if (isset($data['status'])) {
            $validStatuses = ['publish', 'draft', 'pending', 'private'];
            if (in_array($data['status'], $validStatuses)) {
                $product->set_status($data['status']);
                $updated['status'] = $data['status'];
            }
        }

        try {
            $product->save();

            return $this->success('Product updated successfully', [
                'product_id' => $productId,
                'updated_fields' => $updated,
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to update product: ' . $e->getMessage());
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
        $result = parent::validate($data);

        if (!$result['valid']) {
            return $result;
        }

        // Validate product exists
        if (isset($data['product_id'])) {
            $product = wc_get_product(absint($data['product_id']));
            if (!$product) {
                $result['valid'] = false;
                $result['errors'][] = 'Product not found';
            }
        }

        // Validate price values
        if (isset($data['price']) && floatval($data['price']) < 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Price cannot be negative';
        }

        if (isset($data['sale_price']) && $data['sale_price'] !== '' && floatval($data['sale_price']) < 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Sale price cannot be negative';
        }

        // Validate sale price is less than regular price
        if (isset($data['price']) && isset($data['sale_price']) && $data['sale_price'] !== '') {
            if (floatval($data['sale_price']) >= floatval($data['price'])) {
                $result['valid'] = false;
                $result['errors'][] = 'Sale price must be less than regular price';
            }
        }

        return $result;
    }
}
