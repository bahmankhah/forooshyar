<?php
/**
 * Create Discount Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class CreateDiscountAction extends AbstractAction
{
    /** @var string */
    protected $type = 'create_discount';

    /** @var string */
    protected $name = 'Create Discount';

    /** @var string */
    protected $description = 'Create WooCommerce coupon code';

    /** @var array */
    protected $requiredFields = ['code', 'amount'];

    /** @var array */
    protected $optionalFields = ['type', 'expiry_date', 'customer_id', 'product_ids', 'usage_limit'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data)
    {
        $code = sanitize_text_field($data['code']);
        $amount = floatval($data['amount']);
        $type = $this->getField($data, 'type', 'percent');
        $expiryDate = $this->getField($data, 'expiry_date');
        $customerId = $this->getField($data, 'customer_id');
        $productIds = $this->getField($data, 'product_ids', []);
        $usageLimit = $this->getField($data, 'usage_limit', 1);

        // Check if coupon already exists
        $existingCoupon = wc_get_coupon_id_by_code($code);
        if ($existingCoupon) {
            return $this->error('Coupon code already exists');
        }

        // Create coupon
        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_amount($amount);

        // Set discount type
        switch ($type) {
            case 'fixed':
            case 'fixed_cart':
                $coupon->set_discount_type('fixed_cart');
                break;
            case 'fixed_product':
                $coupon->set_discount_type('fixed_product');
                break;
            case 'percent':
            default:
                $coupon->set_discount_type('percent');
                break;
        }

        // Set expiry date
        if ($expiryDate) {
            $coupon->set_date_expires(strtotime($expiryDate));
        }

        // Restrict to specific customer
        if ($customerId) {
            $customer = new \WC_Customer($customerId);
            if ($customer->get_email()) {
                $coupon->set_email_restrictions([$customer->get_email()]);
            }
        }

        // Restrict to specific products
        if (!empty($productIds)) {
            $coupon->set_product_ids($productIds);
        }

        // Set usage limit
        $coupon->set_usage_limit($usageLimit);

        // Set individual use
        $coupon->set_individual_use(true);

        try {
            $couponId = $coupon->save();

            return $this->success('Coupon created successfully', [
                'coupon_id' => $couponId,
                'code' => $code,
                'amount' => $amount,
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to create coupon: ' . $e->getMessage());
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

        // Validate amount
        if (isset($data['amount']) && floatval($data['amount']) <= 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Amount must be greater than 0';
        }

        // Validate discount type
        $validTypes = ['percent', 'fixed', 'fixed_cart', 'fixed_product'];
        if (isset($data['type']) && !in_array($data['type'], $validTypes)) {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid discount type';
        }

        // Validate percent amount
        if (isset($data['type']) && $data['type'] === 'percent' && isset($data['amount'])) {
            if (floatval($data['amount']) > 100) {
                $result['valid'] = false;
                $result['errors'][] = 'Percent discount cannot exceed 100%';
            }
        }

        return $result;
    }
}
