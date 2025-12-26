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
    protected $requiredFields = [];
    protected $optionalFields = ['customer_id', 'entity_id', 'entity_type', 'reward_type', 'reward_value', 'reward_message', 'reward_code', 'amount', 'reason', 'expiry_date'];

    public function execute(array $data)
    {
        // Support both customer_id and entity_id
        $customerId = $this->getField($data, 'customer_id');
        if (!$customerId) {
            $entityType = $this->getField($data, 'entity_type');
            $entityId = $this->getField($data, 'entity_id');
            if ($entityType === 'customer' && $entityId) {
                $customerId = $entityId;
            }
        }
        
        if (!$customerId) {
            return $this->error(__('شناسه مشتری یافت نشد', 'forooshyar'));
        }
        
        $customerId = absint($customerId);
        $customer = new \WC_Customer($customerId);

        if (!$customer->get_id()) {
            return $this->error(__('مشتری یافت نشد', 'forooshyar'));
        }
        
        // Support LLM field names
        $rewardType = $this->getField($data, 'reward_type', 'discount');
        $rewardValue = $this->getField($data, 'reward_value', $this->getField($data, 'amount', 10));
        $rewardMessage = $this->getField($data, 'reward_message', $this->getField($data, 'reason', ''));
        $rewardCode = $this->getField($data, 'reward_code', '');
        
        // Generate code if not provided
        if (empty($rewardCode)) {
            $rewardCode = 'LOYALTY-' . $customerId . '-' . strtoupper(wp_generate_password(4, false));
        }

        // Create a coupon as reward
        $coupon = new \WC_Coupon();
        $coupon->set_code($rewardCode);
        $coupon->set_amount(floatval($rewardValue));
        
        // Set discount type based on reward_type
        switch ($rewardType) {
            case 'free_shipping':
                $coupon->set_free_shipping(true);
                $coupon->set_amount(0);
                break;
            case 'fixed':
            case 'gift':
                $coupon->set_discount_type('fixed_cart');
                break;
            case 'percent':
            case 'discount':
            default:
                $coupon->set_discount_type('percent');
                break;
        }
        
        $coupon->set_email_restrictions([$customer->get_email()]);
        $coupon->set_usage_limit(1);
        $coupon->set_individual_use(true);
        
        // Set description/message
        if ($rewardMessage) {
            $coupon->set_description($rewardMessage);
        }

        $expiryDate = $this->getField($data, 'expiry_date');
        if ($expiryDate) {
            $coupon->set_date_expires(strtotime($expiryDate));
        } else {
            // Default 30 days expiry
            $coupon->set_date_expires(strtotime('+30 days'));
        }

        $couponId = $coupon->save();

        return $this->success(__('پاداش وفاداری صادر شد', 'forooshyar'), [
            'coupon_id' => $couponId,
            'code' => $rewardCode,
            'customer_id' => $customerId,
            'reward_type' => $rewardType,
            'reward_value' => $rewardValue,
        ]);
    }
    
    public function validate(array $data)
    {
        $errors = [];
        
        // Check for customer_id or entity_id
        $customerId = $this->getField($data, 'customer_id');
        if (!$customerId) {
            $entityType = $this->getField($data, 'entity_type');
            $entityId = $this->getField($data, 'entity_id');
            if ($entityType === 'customer' && $entityId) {
                $customerId = $entityId;
            }
        }
        
        if (!$customerId) {
            $errors[] = __('شناسه مشتری الزامی است', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
