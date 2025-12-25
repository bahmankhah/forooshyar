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

    /** @var array */
    protected $requiredFields = ['email', 'subject', 'message'];

    /** @var array */
    protected $optionalFields = ['customer_id', 'template'];

    /**
     * Execute the action
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data)
    {
        $email = sanitize_email($data['email']);
        $subject = sanitize_text_field($data['subject']);
        $message = wp_kses_post($data['message']);

        if (!is_email($email)) {
            return $this->error('Invalid email address');
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
            return $this->success('Email sent successfully', [
                'email' => $email,
                'subject' => $subject,
            ]);
        }

        return $this->error('Failed to send email');
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
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$subject}</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c3e50;'>{$subject}</h2>
        <div style='margin: 20px 0;'>
            {$message}
        </div>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='font-size: 12px; color: #999;'>
            This email was sent by {$siteName}
        </p>
    </div>
</body>
</html>";
    }
}
