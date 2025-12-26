<?php
/**
 * Send Email Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class SendEmailAction extends AbstractAction
{
    /** @var string */
    protected $type = 'send_email';

    /** @var string */
    protected $name = 'Send Email';

    /** @var string */
    protected $description = 'Send personalized email to customer';

    /** @var array - No required fields, we'll get email from customer_id or entity_id */
    protected $requiredFields = [];

    /** @var array */
    protected $optionalFields = ['email', 'customer_id', 'entity_id', 'entity_type', 'template', 'email_type'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data)
    {
        $subject = sanitize_text_field($this->getField($data, 'subject', ''));
        $message = wp_kses_post($this->getField($data, 'message', ''));
        
        // Get email - try direct email first, then customer_id, then entity_id
        $email = $this->getField($data, 'email', '');
        
        if (empty($email)) {
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
                $email = $customer->get_email();
            }
        }
        
        if (empty($email) || !is_email($email)) {
            return $this->error(__('آدرس ایمیل معتبر یافت نشد', 'forooshyar'));
        }
        
        if (empty($subject)) {
            return $this->error(__('موضوع ایمیل الزامی است', 'forooshyar'));
        }
        
        if (empty($message)) {
            return $this->error(__('متن ایمیل الزامی است', 'forooshyar'));
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Add from header if configured
        $fromEmail = $this->settings->get('notify_admin_email');
        if ($fromEmail) {
            $headers[] = 'From: ' . get_bloginfo('name') . ' <' . $fromEmail . '>';
        }

        // Wrap message in basic HTML template
        $htmlMessage = $this->wrapInTemplate($message, $subject);

        $sent = wp_mail($email, $subject, $htmlMessage, $headers);

        if ($sent) {
            return $this->success(__('ایمیل با موفقیت ارسال شد', 'forooshyar'), [
                'email' => $email,
                'subject' => $subject,
            ]);
        }

        return $this->error(__('خطا در ارسال ایمیل', 'forooshyar'));
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
        
        // Check for subject
        if (empty($this->getField($data, 'subject', ''))) {
            $errors[] = __('موضوع ایمیل الزامی است', 'forooshyar');
        }
        
        // Check for message
        if (empty($this->getField($data, 'message', ''))) {
            $errors[] = __('متن ایمیل الزامی است', 'forooshyar');
        }
        
        // Check for email source (email, customer_id, or entity_id with entity_type=customer)
        $email = $this->getField($data, 'email', '');
        $customerId = $this->getField($data, 'customer_id');
        $entityType = $this->getField($data, 'entity_type');
        $entityId = $this->getField($data, 'entity_id');
        
        if (empty($email) && empty($customerId) && !($entityType === 'customer' && $entityId)) {
            $errors[] = __('آدرس ایمیل یا شناسه مشتری الزامی است', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Wrap message in HTML template
     *
     * @param string $message
     * @param string $subject
     * @return string
     */
    private function wrapInTemplate($message, $subject)
    {
        $siteName = get_bloginfo('name');

        return "
<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <title>{$subject}</title>
</head>
<body style='font-family: Tahoma, Arial, sans-serif; line-height: 1.8; color: #333; direction: rtl;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c3e50;'>{$subject}</h2>
        <div style='margin: 20px 0;'>
            {$message}
        </div>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='font-size: 12px; color: #999;'>
            این ایمیل توسط {$siteName} ارسال شده است
        </p>
    </div>
</body>
</html>";
    }
}
