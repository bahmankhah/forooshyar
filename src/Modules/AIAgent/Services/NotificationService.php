<?php
/**
 * Notification Service
 * 
 * Handles email notifications for AI Agent events
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
            __('[AI Agent] High Priority Action: %s', 'forooshyar'),
            isset($action['action_type']) ? $action['action_type'] : 'Unknown'
        );

        $message = $this->buildHighPriorityMessage($action);

        return $this->send($subject, $message);
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

        $subject = __('[AI Agent] Error Notification', 'forooshyar');
        $message = $this->buildErrorMessage($errorMessage, $context);

        return $this->send($subject, $message);
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
            __('[AI Agent] Daily Summary - %s', 'forooshyar'),
            current_time('Y-m-d')
        );

        $message = $this->buildDailySummaryMessage($stats);

        return $this->send($subject, $message);
    }

    /**
     * Send analysis completed notification
     *
     * @param array $results Analysis results
     * @return bool
     */
    public function notifyAnalysisCompleted(array $results)
    {
        $subject = __('[AI Agent] Analysis Completed', 'forooshyar');
        $message = $this->buildAnalysisCompletedMessage($results);

        return $this->send($subject, $message);
    }

    /**
     * Send action executed notification
     *
     * @param array $action Action data
     * @param array $result Execution result
     * @return bool
     */
    public function notifyActionExecuted(array $action, array $result)
    {
        $subject = sprintf(
            __('[AI Agent] Action Executed: %s', 'forooshyar'),
            isset($action['action_type']) ? $action['action_type'] : 'Unknown'
        );

        $message = $this->buildActionExecutedMessage($action, $result);

        return $this->send($subject, $message);
    }

    /**
     * Send email notification
     *
     * @param string $subject
     * @param string $message
     * @param array $headers
     * @return bool
     */
    private function send($subject, $message, array $headers = [])
    {
        $to = $this->settings->getNotificationEmail();

        if (empty($to)) {
            $this->logger->warning('No notification email configured');
            return false;
        }

        $defaultHeaders = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $headers = array_merge($defaultHeaders, $headers);
        $htmlMessage = $this->wrapInTemplate($subject, $message);

        $sent = wp_mail($to, $subject, $htmlMessage, $headers);

        if ($sent) {
            $this->logger->info('Notification sent', ['subject' => $subject, 'to' => $to]);
        } else {
            $this->logger->error('Failed to send notification', ['subject' => $subject, 'to' => $to]);
        }

        return $sent;
    }

    /**
     * Build high priority action message
     *
     * @param array $action
     * @return string
     */
    private function buildHighPriorityMessage(array $action)
    {
        $actionType = isset($action['action_type']) ? $action['action_type'] : 'Unknown';
        $priority = isset($action['priority_score']) ? $action['priority_score'] : 0;
        $data = isset($action['action_data']) ? $action['action_data'] : [];

        $html = '<h2>' . esc_html__('High Priority Action Detected', 'forooshyar') . '</h2>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__('Action Type', 'forooshyar') . '</strong></td>';
        $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(ucfirst(str_replace('_', ' ', $actionType))) . '</td></tr>';
        $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__('Priority Score', 'forooshyar') . '</strong></td>';
        $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($priority) . '/100</td></tr>';
        $html .= '</table>';

        if (!empty($data)) {
            $html .= '<h3>' . esc_html__('Action Details', 'forooshyar') . '</h3>';
            $html .= '<pre style="background:#f5f5f5;padding:10px;overflow:auto;">' . esc_html(wp_json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
        }

        $html .= '<p><a href="' . esc_url(admin_url('admin.php?page=forooshyar-ai-agent')) . '" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:3px;">' . esc_html__('View in Dashboard', 'forooshyar') . '</a></p>';

        return $html;
    }

    /**
     * Build error message
     *
     * @param string $errorMessage
     * @param array $context
     * @return string
     */
    private function buildErrorMessage($errorMessage, array $context)
    {
        $html = '<h2 style="color:#dc3545;">' . esc_html__('Error Occurred', 'forooshyar') . '</h2>';
        $html .= '<p><strong>' . esc_html__('Error Message:', 'forooshyar') . '</strong></p>';
        $html .= '<p style="background:#f8d7da;padding:10px;border-radius:3px;color:#721c24;">' . esc_html($errorMessage) . '</p>';

        if (!empty($context)) {
            $html .= '<h3>' . esc_html__('Context', 'forooshyar') . '</h3>';
            $html .= '<pre style="background:#f5f5f5;padding:10px;overflow:auto;">' . esc_html(wp_json_encode($context, JSON_PRETTY_PRINT)) . '</pre>';
        }

        $html .= '<p><small>' . esc_html__('Time:', 'forooshyar') . ' ' . esc_html(current_time('Y-m-d H:i:s')) . '</small></p>';

        return $html;
    }

    /**
     * Build daily summary message
     *
     * @param array $stats
     * @return string
     */
    private function buildDailySummaryMessage(array $stats)
    {
        $html = '<h2>' . esc_html__('Daily AI Agent Summary', 'forooshyar') . '</h2>';
        $html .= '<p>' . sprintf(esc_html__('Summary for %s', 'forooshyar'), current_time('Y-m-d')) . '</p>';

        $html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';
        
        if (isset($stats['summary'])) {
            $summary = $stats['summary'];
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__('Analyses Today', 'forooshyar') . '</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['analyses_today']) ? $summary['analyses_today'] : 0) . '</td></tr>';
            
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__('Actions Completed', 'forooshyar') . '</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['completed_today']) ? $summary['completed_today'] : 0) . '</td></tr>';
            
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__('Pending Actions', 'forooshyar') . '</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['pending_actions']) ? $summary['pending_actions'] : 0) . '</td></tr>';
            
            $html .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__('Success Rate', 'forooshyar') . '</strong></td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html(isset($summary['success_rate']) ? $summary['success_rate'] : 0) . '%</td></tr>';
        }
        
        $html .= '</table>';

        $html .= '<p><a href="' . esc_url(admin_url('admin.php?page=forooshyar-ai-agent')) . '" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:3px;">' . esc_html__('View Full Dashboard', 'forooshyar') . '</a></p>';

        return $html;
    }

    /**
     * Build analysis completed message
     *
     * @param array $results
     * @return string
     */
    private function buildAnalysisCompletedMessage(array $results)
    {
        $html = '<h2>' . esc_html__('Analysis Completed', 'forooshyar') . '</h2>';

        if (isset($results['products'])) {
            $html .= '<h3>' . esc_html__('Product Analysis', 'forooshyar') . '</h3>';
            $html .= '<p>' . sprintf(
                esc_html__('Analyzed %d products, generated %d suggestions', 'forooshyar'),
                isset($results['products']['analyzed']) ? $results['products']['analyzed'] : 0,
                isset($results['products']['suggestions']) ? count($results['products']['suggestions']) : 0
            ) . '</p>';
        }

        if (isset($results['customers'])) {
            $html .= '<h3>' . esc_html__('Customer Analysis', 'forooshyar') . '</h3>';
            $html .= '<p>' . sprintf(
                esc_html__('Analyzed %d customers, generated %d suggestions', 'forooshyar'),
                isset($results['customers']['analyzed']) ? $results['customers']['analyzed'] : 0,
                isset($results['customers']['suggestions']) ? count($results['customers']['suggestions']) : 0
            ) . '</p>';
        }

        return $html;
    }

    /**
     * Build action executed message
     *
     * @param array $action
     * @param array $result
     * @return string
     */
    private function buildActionExecutedMessage(array $action, array $result)
    {
        $success = isset($result['success']) && $result['success'];
        $statusColor = $success ? '#28a745' : '#dc3545';
        $statusText = $success ? __('Success', 'forooshyar') : __('Failed', 'forooshyar');

        $html = '<h2>' . esc_html__('Action Executed', 'forooshyar') . '</h2>';
        $html .= '<p><strong>' . esc_html__('Status:', 'forooshyar') . '</strong> <span style="color:' . $statusColor . ';">' . esc_html($statusText) . '</span></p>';
        $html .= '<p><strong>' . esc_html__('Action Type:', 'forooshyar') . '</strong> ' . esc_html(isset($action['action_type']) ? ucfirst(str_replace('_', ' ', $action['action_type'])) : 'Unknown') . '</p>';

        if (isset($result['message'])) {
            $html .= '<p><strong>' . esc_html__('Message:', 'forooshyar') . '</strong> ' . esc_html($result['message']) . '</p>';
        }

        return $html;
    }

    /**
     * Wrap message in HTML email template
     *
     * @param string $subject
     * @param string $content
     * @return string
     */
    private function wrapInTemplate($subject, $content)
    {
        $siteName = get_bloginfo('name');
        $siteUrl = get_bloginfo('url');

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($subject) . '</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="background: #2271b1; color: #fff; padding: 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px;">' . esc_html($siteName) . '</h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">AI Sales Agent</p>
            </div>
            <div style="padding: 30px;">
                ' . $content . '
            </div>
            <div style="background: #f8f9fa; padding: 15px 30px; border-top: 1px solid #eee; font-size: 12px; color: #666;">
                <p style="margin: 0;">' . esc_html__('This is an automated message from AI Sales Agent.', 'forooshyar') . '</p>
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
