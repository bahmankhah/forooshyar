<?php
/**
 * Send SMS Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class SendSmsAction extends AbstractAction
{
    /** @var string */
    protected $type = 'send_sms';

    /** @var string */
    protected $name = 'Send SMS';

    /** @var string */
    protected $description = 'Send SMS to customer';

    /** @var array */
    protected $requiredFields = [];

    /** @var array */
    protected $optionalFields = ['phone', 'message', 'customer_id', 'entity_id', 'entity_type'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data)
    {
        $message = $this->getField($data, 'message', '');
        
        if (empty($message)) {
            return $this->error(__('متن پیامک الزامی است', 'forooshyar'));
        }
        
        // Get phone number - try direct phone first, then customer_id, then entity_id
        $phone = $this->getField($data, 'phone', '');
        
        if (empty($phone)) {
            $customerId = $this->getField($data, 'customer_id');
            if (!$customerId) {
                $entityType = $this->getField($data, 'entity_type');
                $entityId = $this->getField($data, 'entity_id');
                if ($entityType === 'customer' && $entityId) {
                    $customerId = $entityId;
                }
            }
            
            if ($customerId) {
                $customer = new \WC_Customer($customerId);
                $phone = $customer->get_billing_phone();
            }
        }
        
        if (empty($phone)) {
            return $this->error(__('شماره تلفن یافت نشد', 'forooshyar'));
        }
        
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if SMS provider is configured
        $smsProvider = $this->settings->get('sms_provider', '');
        
        if (empty($smsProvider)) {
            // Store SMS for manual sending
            $smsData = [
                'phone' => $phone,
                'message' => $message,
                'created_at' => current_time('mysql'),
                'status' => 'pending_manual',
            ];
            
            $smsId = 'sms_' . time() . '_' . wp_generate_password(4, false, false);
            update_option('aiagent_' . $smsId, $smsData);
            
            return $this->success(__('پیامک برای ارسال دستی ذخیره شد (ارائه‌دهنده پیامک پیکربندی نشده)', 'forooshyar'), [
                'id' => $smsId,
                'phone' => $phone,
                'message' => $message,
                'manual' => true,
            ]);
        }
        
        // Try to send via configured SMS provider
        $sent = apply_filters('aiagent_send_sms', false, $phone, $message, $smsProvider);
        
        if ($sent) {
            return $this->success(__('پیامک با موفقیت ارسال شد', 'forooshyar'), [
                'phone' => $phone,
                'message' => $message,
            ]);
        }
        
        // If filter didn't handle it, store for manual sending
        $smsData = [
            'phone' => $phone,
            'message' => $message,
            'created_at' => current_time('mysql'),
            'status' => 'pending_manual',
        ];
        
        $smsId = 'sms_' . time() . '_' . wp_generate_password(4, false, false);
        update_option('aiagent_' . $smsId, $smsData);
        
        return $this->success(__('پیامک برای ارسال دستی ذخیره شد', 'forooshyar'), [
            'id' => $smsId,
            'phone' => $phone,
            'message' => $message,
            'manual' => true,
        ]);
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
        
        // Check for message
        if (empty($this->getField($data, 'message', ''))) {
            $errors[] = __('متن پیامک الزامی است', 'forooshyar');
        }
        
        // Check for phone source (phone, customer_id, or entity_id with entity_type=customer)
        $phone = $this->getField($data, 'phone', '');
        $customerId = $this->getField($data, 'customer_id');
        $entityType = $this->getField($data, 'entity_type');
        $entityId = $this->getField($data, 'entity_id');
        
        if (empty($phone) && empty($customerId) && !($entityType === 'customer' && $entityId)) {
            $errors[] = __('شماره تلفن یا شناسه مشتری الزامی است', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
