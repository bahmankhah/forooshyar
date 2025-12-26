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

    /** @var array - Accept both LLM field names and legacy field names */
    protected $requiredFields = [];

    /** @var array */
    protected $optionalFields = ['type', 'expiry_date', 'customer_id', 'product_ids', 'usage_limit', 'valid_days', 'description'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data)
    {
        // Support both LLM field names and legacy field names
        $code = sanitize_text_field($this->getField($data, 'discount_code', $this->getField($data, 'code', '')));
        $amount = floatval($this->getField($data, 'discount_percent', $this->getField($data, 'amount', 0)));
        $type = $this->getField($data, 'type', 'percent');
        $validDays = intval($this->getField($data, 'valid_days', 30));
        $customerId = $this->getField($data, 'customer_id', $this->getField($data, 'entity_id'));
        $productIds = $this->getField($data, 'product_ids', []);
        $usageLimit = $this->getField($data, 'usage_limit', 1);
        
        // If no code provided, generate one
        if (empty($code)) {
            $code = 'AI' . strtoupper(wp_generate_password(8, false));
        }
        
        // Calculate expiry date from valid_days
        $expiryDate = $this->getField($data, 'expiry_date');
        if (!$expiryDate && $validDays > 0) {
            $expiryDate = date('Y-m-d', strtotime("+{$validDays} days"));
        }

        // Check if coupon already exists
        $existingCoupon = wc_get_coupon_id_by_code($code);
        if ($existingCoupon) {
            // Append random suffix to make unique
            $code = $code . '_' . wp_generate_password(4, false, false);
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
        $entityType = $this->getField($data, 'entity_type');
        if ($entityType === 'customer' && $customerId) {
            $customer = new \WC_Customer($customerId);
            if ($customer->get_email()) {
                $coupon->set_email_restrictions([$customer->get_email()]);
            }
        }
        
        // If entity_type is product, restrict to that product
        $entityId = $this->getField($data, 'entity_id');
        if ($entityType === 'product' && $entityId) {
            $productIds = [$entityId];
        }

        // Restrict to specific products
        if (!empty($productIds)) {
            $coupon->set_product_ids($productIds);
        }

        // Set usage limit
        $coupon->set_usage_limit($usageLimit);

        // Set individual use
        $coupon->set_individual_use(true);
        
        // Set description if provided
        $description = $this->getField($data, 'description', '');
        if ($description) {
            $coupon->set_description($description);
        }

        try {
            $couponId = $coupon->save();

            return $this->success(__('کد تخفیف با موفقیت ایجاد شد', 'forooshyar'), [
                'coupon_id' => $couponId,
                'code' => $code,
                'amount' => $amount,
                'type' => $type,
                'expiry_date' => $expiryDate,
            ]);
        } catch (\Exception $e) {
            return $this->error(__('خطا در ایجاد کد تخفیف: ', 'forooshyar') . $e->getMessage());
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
        
        // Check for amount (either discount_percent or amount)
        $amount = $this->getField($data, 'discount_percent', $this->getField($data, 'amount', 0));
        if (floatval($amount) <= 0) {
            $errors[] = __('مقدار تخفیف باید بیشتر از صفر باشد', 'forooshyar');
        }

        // Validate discount type
        $type = $this->getField($data, 'type', 'percent');
        $validTypes = ['percent', 'fixed', 'fixed_cart', 'fixed_product'];
        if (!in_array($type, $validTypes)) {
            $errors[] = __('نوع تخفیف نامعتبر است', 'forooshyar');
        }

        // Validate percent amount
        if ($type === 'percent' && floatval($amount) > 100) {
            $errors[] = __('درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد', 'forooshyar');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
