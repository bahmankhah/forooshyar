<?php
/**
 * Loyalty Reward Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class LoyaltyRewardAction extends AbstractAction
{
    protected $type = 'loyalty_reward';
    protected $name = 'Loyalty Reward';
    protected $description = 'Issue loyalty reward to customer';
    protected $requiredFields = ['customer_id', 'reward_type', 'amount'];
    protected $optionalFields = ['reason', 'expiry_date'];

    public function execute(array $data)
    {
        $customerId = absint($data['customer_id']);
        $customer = new \WC_Customer($customerId);

        if (!$customer->get_id()) {
            return $this->error('Customer not found');
        }

        // Create a coupon as reward
        $code = 'LOYALTY-' . $customerId . '-' . time();
        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_amount(floatval($data['amount']));
        $coupon->set_discount_type($data['reward_type'] === 'percent' ? 'percent' : 'fixed_cart');
        $coupon->set_email_restrictions([$customer->get_email()]);
        $coupon->set_usage_limit(1);
        $coupon->set_individual_use(true);

        if (isset($data['expiry_date'])) {
            $coupon->set_date_expires(strtotime($data['expiry_date']));
        }

        $couponId = $coupon->save();

        return $this->success('Loyalty reward issued', [
            'coupon_id' => $couponId,
            'code' => $code,
            'customer_id' => $customerId,
        ]);
    }
}
