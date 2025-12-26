<?php
/**
 * Notification Service
 * 
 * Handles email and SMS notifications for AI Agent events
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

class NotificationService
{
    /** @var SettingsManager */
    private $settings;

    /** @var Logger */
    private $logger;

    /**
     * @param SettingsManager $settings
     * @param Logger $logger
     */
    public function __construct(SettingsManager $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Send notification for high priority action
     *
     * @param array $action Action data
     * @return bool
     */
    public function notifyHighPriorityAction(array $action)
    {
        if (!$this->settings->isNotificationEnabled('high_priority')) {
            return false;
        }

        $subject = sprintf(
            __('[دستیار هوشمند] اقدام با اولویت بالا: %s', 'forooshyar'),
            isset($action['action_type']) ? $this->getActionTypeLabel($action['action_type']) : 'نامشخص'
        );

        $emailMessage = $this->buildHighPriorityEmailMessage($action);
        $smsMessage = $this->buildHighPrioritySmsMessage($action);

        return $this->sendToAllRecipients($subject, $emailMessage, $smsMessage);
    }

    /**
     * Send notification for error
     *
     * @param string $errorMessage
     * @param array $context
     * @return bool
     */
    public function notifyError($errorMessage, array $context = [])
    {
        if (!$this->settings->isNotificationEnabled('errors')) {
            return false;
        }

        $subject = __('[دستیار هوشمند] خطا در سیستم', 'forooshyar');
        $emailMessage = $this->buildErrorEmailMessage($errorMessage, $context);
        $smsMessage = $this->buildErrorSmsMessage($errorMessage);

        return $this->sendToAllRecipients($subject, $emailMessage, $smsMessage);
    }

    /**
     * Send daily summary notification
     *
     * @param array $stats Statistics data
     * @return bool
     */
    public function sendDailySummary(array $stats)
    {
        if (!$this->settings->isNotificationEnabled('daily_summary')) {
            return false;
        }

        $subject = sprintf(
            __('[دستیار هوشمند] خلاصه روزانه - %s', 'forooshyar'),
            current_time('Y-m-d')
        );

        $emailMessage = $this->buildDailySummaryEmailMessage($stats);
        $smsMessage = $this->buildDailySummarySmsMessage($stats);

        return $this->sendToAllRecipients($subject, $emailMessage, $smsMessage);
    }

    /**
     * Send notification to all configured recipients via all configured methods
     *
     * @param string $subject
     * @param string $emailMessage HTML message for email
     * @param string $smsMessage Short text message for SMS
     * @return bool
     */
    private function sendToAllRecipients($subject, $emailMessage, $smsMessage)
    {
        $recipients = $this->settings->getNotificationRecipients();
        $methods = $this->settings->getNotificationMethods();
        
        if (empty($recipients)) {
            $this->logger->warning('No notification recipients configured');
            return false;
        }

        $success = false;

        foreach ($recipients as $userId) {
            $user = get_user_by('ID', $userId);
            if (!$user) {
                continue;
            }

            // Send via email
            if (in_array('email', $methods, true) && !empty($user->user_email)) {
                $sent = $this->sendEmail($user->user_email, $subject, $emailMessage);
                if ($sent) {
                    $success = true;
                }
            }

            // Send via SMS
            if (in_array('sms', $methods, true)) {
                $phone = $this->getUserPhone($userId);
                if (!empty($phone)) {
                    $sent = $this->sendSms($phone, $smsMessage);
                    if ($sent) {
                        $success = true;
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Get user phone number from user meta
     *
     * @param int $userId
     * @return string|null
     */
    private function getUserPhone($userId)
    {
        // Try common phone meta keys
        $phoneKeys = ['billing_phone', 'phone', 'mobile', 'phone_number'];
        
        foreach ($phoneKeys as $key) {
            $phone = get_user_meta($userId, $key, true);
            if (!empty($phone)) {
                return $phone;
            }
        }

        return null;
    }

    /**
     * Send email notification
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @return bool
     */
    private function sendEmail($to, $subject, $message)
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $htmlMessage = $this->wrapInEmailTemplate($subject, $message);

        $sent = wp_mail($to, $subject, $htmlMessage, $headers);

        if ($sent) {
            $this->logger->info('Email notification sent', ['to' => $to, 'subject' => $subject]);
        } else {
            $this->logger->error('Failed to send email notification', ['to' => $to, 'subject' => $subject]);
        }

        return $sent;
    }

    /**
     * Send SMS notification
     * 
     * Uses the aiagent_send_notification_sms filter which must be implemented by the user.
     * 
     * Example implementation:
     * add_filter('aiagent_send_notification_sms', function($sent, $phone, $message) {
     *     // Your SMS sending logic here
     *     return true; // Return true if sent successfully
     * }, 10, 3);
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    private function sendSms($phone, $message)
    {
        /**
         * Filter to send SMS notification
         * 
         * @param bool $sent Whether the SMS was sent (default false)
         * @param string $phone Phone number
         * @param string $message SMS message text
         */
        $sent = apply_filters('aiagent_send_notification_sms', false, $phone, $message);

        if ($sent) {
            $this->logger->info('SMS notification sent', ['phone' => $phone]);
        } else {
            $this->logger->debug('SMS notification not sent (filter not implemented or failed)', ['phone' => $phone]);
        }

        return (bool) $sent;
    }

    /**
     * Get action type label in Persian
     *
     * @param string $actionType
     * @return string
     */
    private function getActionTypeLabel($actionType)
    {
        $labels = [
            'send_email' => 'ارسال ایمیل',
            'send_sms' => 'ارسال پیامک',
            'create_discount' => 'ایجاد تخفیف',
            'update_product' => 'بروزرسانی محصول',
            'create_campaign' => 'ایجاد کمپین',
            'schedule_followup' => 'زمان‌بندی پیگیری',
            'create_bundle' => 'ایجاد بسته',
            'inventory_alert' => 'هشدار موجودی',
            'loyalty_reward' => 'پاداش وفاداری',
            'schedule_price_change' => 'زمان‌بندی تغییر قیمت',
        ];

        return isset($labels[$actionType]) ? $labels[$actionType] : $actionType;
    }

    /**
     * Build high priority action email message
     *
     * @param array $action
     * @return string
     */
    private function buildHighPriorityEmailMessage(array $action)
    {
        $actionType = isset($action['action_type']) ? $action['action_type'] : 'Unknown';
        $priority = isset($action['priority_score']) ? $action['priority_score'] : 0;
        $data = isset($action['action_data']) ? $action['action_data'] : [];

        $html = '<h2>اقدام با اولویت بالا شناسایی شد</h2>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>نوع اقدام</strong></td>';
        $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($this->getActionTypeLabel($actionType)) . '</td></tr>';
        $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>امتیاز اولویت</strong></td>';
        $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($priority) . '/100</td></tr>';
        $html .= '</table>';

        if (!empty($data['reasoning'])) {
            $html .= '<h3>دلیل پیشنهاد</h3>';
            $html .= '<p style="background:#f5f5f5;padding:10px;border-radius:4px;">' . esc_html($data['reasoning']) . '</p>';
        }

        $html .= '<p style="margin-top:20px;"><a href="' . esc_url(admin_url('admin.php?page=forooshyar-ai-agent')) . '" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:3px;">مشاهده در داشبورد</a></p>';

        return $html;
    }

    /**
     * Build high priority action SMS message
     *
     * @param array $action
     * @return string
     */
    private function buildHighPrioritySmsMessage(array $action)
    {
        $actionType = isset($action['action_type']) ? $this->getActionTypeLabel($action['action_type']) : 'نامشخص';
        $priority = isset($action['priority_score']) ? $action['priority_score'] : 0;

        return sprintf(
            'دستیار هوشمند: اقدام "%s" با اولویت %d شناسایی شد. برای مشاهده وارد پنل شوید.',
            $actionType,
            $priority
        );
    }

    /**
     * Build error email message
     *
     * @param string $errorMessage
     * @param array $context
     * @return string
     */
    private function buildErrorEmailMessage($errorMessage, array $context)
    {
        $html = '<h2 style="color:#dc3545;">خطا در سیستم دستیار هوشمند</h2>';
        $html .= '<p><strong>پیام خطا:</strong></p>';
        $html .= '<p style="background:#f8d7da;padding:10px;border-radius:3px;color:#721c24;">' . esc_html($errorMessage) . '</p>';

        if (!empty($context)) {
            $html .= '<h3>جزئیات</h3>';
            $html .= '<pre style="background:#f5f5f5;padding:10px;overflow:auto;font-size:12px;">' . esc_html(wp_json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        }

        $html .= '<p><small>زمان: ' . esc_html(current_time('Y-m-d H:i:s')) . '</small></p>';

        return $html;
    }

    /**
     * Build error SMS message
     *
     * @param string $errorMessage
     * @return string
     */
    private function buildErrorSmsMessage($errorMessage)
    {
        // Truncate for SMS
        $shortMessage = mb_substr($errorMessage, 0, 50);
        if (mb_strlen($errorMessage) > 50) {
            $shortMessage .= '...';
        }

        return sprintf('خطا در دستیار هوشمند: %s', $shortMessage);
    }

    /**
     * Build daily summary email message
     *
     * @param array $stats
     * @return string
     */
    private function buildDailySummaryEmailMessage(array $stats)
    {
        $html = '<h2>خلاصه روزانه دستیار هوشمند</h2>';
        $html .= '<p>خلاصه فعالیت‌های ' . current_time('Y-m-d') . '</p>';

        $html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';
        
        if (isset($stats['summary'])) {
            $summary = $stats['summary'];
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>تحلیل‌های امروز</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['analyses_today']) ? $summary['analyses_today'] : 0) . '</td></tr>';
            
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>اقدامات انجام شده</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['completed_today']) ? $summary['completed_today'] : 0) . '</td></tr>';
            
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>اقدامات در انتظار</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['pending_actions']) ? $summary['pending_actions'] : 0) . '</td></tr>';
            
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>نرخ موفقیت</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['success_rate']) ? $summary['success_rate'] : 0) . '%</td></tr>';
        }
        
        $html .= '</table>';

        $html .= '<p><a href="' . esc_url(admin_url('admin.php?page=forooshyar-ai-agent')) . '" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:3px;">مشاهده داشبورد کامل</a></p>';

        return $html;
    }

    /**
     * Build daily summary SMS message
     *
     * @param array $stats
     * @return string
     */
    private function buildDailySummarySmsMessage(array $stats)
    {
        $summary = isset($stats['summary']) ? $stats['summary'] : [];
        $analyses = isset($summary['analyses_today']) ? $summary['analyses_today'] : 0;
        $completed = isset($summary['completed_today']) ? $summary['completed_today'] : 0;
        $pending = isset($summary['pending_actions']) ? $summary['pending_actions'] : 0;

        return sprintf(
            'خلاصه دستیار هوشمند: %d تحلیل، %d اقدام انجام شده، %d در انتظار',
            $analyses,
            $completed,
            $pending
        );
    }

    /**
     * Wrap message in HTML email template
     *
     * @param string $subject
     * @param string $content
     * @return string
     */
    private function wrapInEmailTemplate($subject, $content)
    {
        $siteName = get_bloginfo('name');
        $siteUrl = get_bloginfo('url');

        return '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($subject) . '</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; line-height: 1.8; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; direction: rtl;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="background: #2271b1; color: #fff; padding: 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 20px;">' . esc_html($siteName) . '</h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">دستیار هوشمند فروش</p>
            </div>
            <div style="padding: 30px;">
                ' . $content . '
            </div>
            <div style="background: #f8f9fa; padding: 15px 30px; border-top: 1px solid #eee; font-size: 12px; color: #666;">
                <p style="margin: 0;">این پیام به صورت خودکار از دستیار هوشمند فروش ارسال شده است.</p>
                <p style="margin: 5px 0 0;"><a href="' . esc_url($siteUrl) . '" style="color: #2271b1;">' . esc_html($siteName) . '</a></p>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Schedule daily summary email
     *
     * @return void
     */
    public function scheduleDailySummary()
    {
        if (!wp_next_scheduled('aiagent_daily_summary')) {
            // Schedule for 9 AM local time
            $timestamp = strtotime('tomorrow 9:00:00', current_time('timestamp'));
            wp_schedule_event($timestamp, 'daily', 'aiagent_daily_summary');
        }
    }

    /**
     * Unschedule daily summary email
     *
     * @return void
     */
    public function unscheduleDailySummary()
    {
        $timestamp = wp_next_scheduled('aiagent_daily_summary');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'aiagent_daily_summary');
        }
    }
}
