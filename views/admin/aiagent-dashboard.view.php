<?php
/**
 * AI Agent Dashboard View
 * Persian interface for AI Sales Agent dashboard
 * 
 * @var array $stats
 * @var array $status
 * @var array $dashboardData
 */

if (!defined('ABSPATH')) exit;

$tierNames = [
    'free' => 'رایگان',
    'basic' => 'پایه',
    'pro' => 'حرفه‌ای',
    'enterprise' => 'سازمانی',
];
$currentTierName = $tierNames[$status['tier']] ?? $status['tier'];

$actionTypeLabels = [
    'send_email' => 'ارسال ایمیل',
    'send_sms' => 'ارسال پیامک',
    'create_discount' => 'ایجاد تخفیف',
    'update_product' => 'بروزرسانی محصول',
    'create_campaign' => 'ایجاد کمپین',
    'schedule_followup' => 'زمان‌بندی پیگیری',
    'create_bundle' => 'ایجاد بسته',
    'inventory_alert' => 'هشدار موجودی',
    'loyalty_reward' => 'پاداش وفاداری',
    'schedule_price_change' => 'تغییر قیمت',
];
$statusLabels = [
    'pending' => 'در انتظار تأیید',
    'approved' => 'تأیید شده',
    'completed' => 'انجام شده',
    'failed' => 'ناموفق',
    'cancelled' => 'لغو شده',
];
$actionTypeIcons = [
    'send_email' => 'dashicons-email-alt',
    'send_sms' => 'dashicons-smartphone',
    'create_discount' => 'dashicons-tag',
    'update_product' => 'dashicons-edit',
    'create_campaign' => 'dashicons-megaphone',
    'schedule_followup' => 'dashicons-calendar-alt',
    'create_bundle' => 'dashicons-archive',
    'inventory_alert' => 'dashicons-warning',
    'loyalty_reward' => 'dashicons-star-filled',
    'schedule_price_change' => 'dashicons-chart-line',
];
?>
<div class="wrap aiagent-dashboard" dir="rtl">
    <h1><?php _e('داشبورد دستیار فروش هوشمند', 'forooshyar'); ?></h1>

    <?php if (!$status['enabled']): ?>
    <div class="notice notice-warning">
        <p><?php _e('ماژول دستیار هوشمند غیرفعال است. برای استفاده، آن را در تنظیمات فعال کنید.', 'forooshyar'); ?></p>
    </div>
    <?php endif; ?>

    <div class="aiagent-header">
        <div class="aiagent-status">
            <span class="status-badge <?php echo $status['enabled'] ? 'active' : 'inactive'; ?>">
                <?php echo $status['enabled'] ? __('فعال', 'forooshyar') : __('غیرفعال', 'forooshyar'); ?>
            </span>
            <span class="tier-badge"><?php echo esc_html($currentTierName); ?></span>
        </div>
        <div class="aiagent-header-actions">
            <button type="button" class="button button-primary" id="run-analysis" <?php echo !$status['enabled'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('شروع تحلیل', 'forooshyar'); ?>
            </button>
            <button type="button" class="button button-secondary" id="cancel-analysis" style="display:none;">
                <?php _e('لغو تحلیل', 'forooshyar'); ?>
            </button>
        </div>
    </div>

    <!-- Analysis Progress Section -->
    <div id="analysis-progress-section" class="aiagent-progress-box" style="display:none;">
        <div class="progress-header">
            <span class="progress-status"><?php _e('در حال تحلیل...', 'forooshyar'); ?></span>
            <span class="progress-percent">0%</span>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width: 0%"></div>
        </div>
        <div class="progress-details">
            <span id="progress-products"><?php _e('محصولات:', 'forooshyar'); ?> 0/0</span>
            <span id="progress-customers"><?php _e('مشتریان:', 'forooshyar'); ?> 0/0</span>
            <span id="progress-actions"><?php _e('اقدامات:', 'forooshyar'); ?> 0</span>
        </div>
        <div class="progress-current" id="current-item"></div>
    </div>

    <!-- Stats Cards -->
    <div class="aiagent-stats-grid">
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-clock"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['pending_actions']); ?></span>
                <span class="stat-label"><?php _e('اقدامات در انتظار', 'forooshyar'); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-yes-alt"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['completed_today']); ?></span>
                <span class="stat-label"><?php _e('انجام شده امروز', 'forooshyar'); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-chart-line"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['success_rate']); ?>%</span>
                <span class="stat-label"><?php _e('نرخ موفقیت', 'forooshyar'); ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-search"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['analyses_today']); ?></span>
                <span class="stat-label"><?php _e('تحلیل‌های امروز', 'forooshyar'); ?></span>
            </div>
        </div>
    </div>

    <!-- Pending Actions - Need Approval -->
    <?php 
    $pendingActions = array_filter($dashboardData['recent_actions'], function($a) {
        return $a['status'] === 'pending';
    });
    ?>
    <?php if (!empty($pendingActions)): ?>
    <div class="aiagent-section">
        <h3><span class="dashicons dashicons-warning"></span> <?php _e('اقدامات در انتظار تأیید', 'forooshyar'); ?></h3>
        <div class="action-cards">
            <?php foreach ($pendingActions as $action): 
                $typeLabel = $actionTypeLabels[$action['action_type']] ?? $action['action_type'];
                $icon = $actionTypeIcons[$action['action_type']] ?? 'dashicons-admin-generic';
                $actionData = $action['action_data'] ?? [];
                $reasoning = $actionData['reasoning'] ?? '';
            ?>
            <div class="action-card priority-<?php echo $action['priority_score'] >= 70 ? 'high' : ($action['priority_score'] >= 50 ? 'medium' : 'low'); ?>">
                <div class="action-card-header">
                    <span class="action-icon dashicons <?php echo esc_attr($icon); ?>"></span>
                    <span class="action-type"><?php echo esc_html($typeLabel); ?></span>
                    <span class="action-priority"><?php echo esc_html($action['priority_score']); ?></span>
                </div>
                <div class="action-card-body">
                    <?php if ($reasoning): ?>
                    <p class="action-reasoning"><?php echo esc_html($reasoning); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($actionData)): ?>
                    <div class="action-details">
                        <?php 
                        unset($actionData['reasoning']);
                        foreach ($actionData as $key => $value): 
                            if (is_array($value)) $value = wp_json_encode($value);
                        ?>
                        <span class="detail-item"><strong><?php echo esc_html($key); ?>:</strong> <?php echo esc_html($value); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="action-card-footer">
                    <span class="action-date"><?php echo esc_html($action['created_at']); ?></span>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary btn-approve-action" data-id="<?php echo esc_attr($action['id']); ?>">
                            <span class="dashicons dashicons-yes"></span> <?php _e('تأیید', 'forooshyar'); ?>
                        </button>
                        <button type="button" class="button btn-execute-action" data-id="<?php echo esc_attr($action['id']); ?>">
                            <span class="dashicons dashicons-controls-play"></span> <?php _e('اجرا', 'forooshyar'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approved Actions - Ready to Execute -->
    <?php 
    $approvedActions = array_filter($dashboardData['recent_actions'], function($a) {
        return $a['status'] === 'approved';
    });
    ?>
    <?php if (!empty($approvedActions)): ?>
    <div class="aiagent-section">
        <h3><span class="dashicons dashicons-yes-alt"></span> <?php _e('اقدامات تأیید شده', 'forooshyar'); ?></h3>
        <div class="action-cards">
            <?php foreach ($approvedActions as $action): 
                $typeLabel = $actionTypeLabels[$action['action_type']] ?? $action['action_type'];
                $icon = $actionTypeIcons[$action['action_type']] ?? 'dashicons-admin-generic';
                $actionData = $action['action_data'] ?? [];
                $reasoning = $actionData['reasoning'] ?? '';
            ?>
            <div class="action-card approved">
                <div class="action-card-header">
                    <span class="action-icon dashicons <?php echo esc_attr($icon); ?>"></span>
                    <span class="action-type"><?php echo esc_html($typeLabel); ?></span>
                    <span class="action-priority"><?php echo esc_html($action['priority_score']); ?></span>
                </div>
                <div class="action-card-body">
                    <?php if ($reasoning): ?>
                    <p class="action-reasoning"><?php echo esc_html($reasoning); ?></p>
                    <?php endif; ?>
                </div>
                <div class="action-card-footer">
                    <span class="action-date"><?php echo esc_html($action['created_at']); ?></span>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary btn-execute-action" data-id="<?php echo esc_attr($action['id']); ?>">
                            <span class="dashicons dashicons-controls-play"></span> <?php _e('اجرا', 'forooshyar'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Analyses with AI Comments -->
    <div class="aiagent-section">
        <h3><span class="dashicons dashicons-lightbulb"></span> <?php _e('تحلیل‌های اخیر', 'forooshyar'); ?></h3>
        <?php if (!empty($dashboardData['recent_analyses'])): ?>
        <div class="analysis-cards">
            <?php foreach ($dashboardData['recent_analyses'] as $analysis): 
                $analysisData = $analysis['analysis_data'] ?? '';
                // Handle both string and array formats
                if (is_array($analysisData)) {
                    $analysisText = isset($analysisData['analysis']) ? $analysisData['analysis'] : wp_json_encode($analysisData);
                } else {
                    $analysisText = $analysisData;
                }
                $suggestions = $analysis['suggestions'] ?? [];
                $entityName = '';
                if ($analysis['entity_type'] === 'product') {
                    $product = wc_get_product($analysis['entity_id']);
                    $entityName = $product ? $product->get_name() : 'محصول #' . $analysis['entity_id'];
                    $entityIcon = 'dashicons-cart';
                } else {
                    $entityName = 'مشتری #' . $analysis['entity_id'];
                    $entityIcon = 'dashicons-admin-users';
                }
            ?>
            <div class="analysis-card">
                <div class="analysis-card-header">
                    <span class="entity-icon dashicons <?php echo $entityIcon; ?>"></span>
                    <span class="entity-name"><?php echo esc_html($entityName); ?></span>
                    <span class="analysis-score priority-<?php echo $analysis['priority_score'] >= 70 ? 'high' : ($analysis['priority_score'] >= 50 ? 'medium' : 'low'); ?>">
                        <?php echo esc_html($analysis['priority_score']); ?>
                    </span>
                </div>
                <div class="analysis-card-body">
                    <?php if ($analysisText): ?>
                    <div class="ai-comment">
                        <span class="dashicons dashicons-format-chat"></span>
                        <p><?php echo esc_html($analysisText); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($suggestions)): ?>
                    <div class="suggestions-summary">
                        <strong><?php _e('پیشنهادات:', 'forooshyar'); ?></strong>
                        <?php foreach (array_slice($suggestions, 0, 3) as $suggestion): 
                            $suggestionType = $actionTypeLabels[$suggestion['type']] ?? $suggestion['type'];
                        ?>
                        <span class="suggestion-tag"><?php echo esc_html($suggestionType); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($suggestions) > 3): ?>
                        <span class="suggestion-more">+<?php echo count($suggestions) - 3; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="analysis-card-footer">
                    <span class="analysis-date"><?php echo esc_html($analysis['created_at']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="no-data"><?php _e('هنوز تحلیلی انجام نشده است. روی "شروع تحلیل" کلیک کنید.', 'forooshyar'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Completed Actions History -->
    <?php 
    $completedActions = array_filter($dashboardData['recent_actions'], function($a) {
        return in_array($a['status'], ['completed', 'failed']);
    });
    ?>
    <?php if (!empty($completedActions)): ?>
    <div class="aiagent-section">
        <h3><span class="dashicons dashicons-backup"></span> <?php _e('تاریخچه اقدامات', 'forooshyar'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('نوع', 'forooshyar'); ?></th>
                    <th><?php _e('وضعیت', 'forooshyar'); ?></th>
                    <th><?php _e('اولویت', 'forooshyar'); ?></th>
                    <th><?php _e('تاریخ', 'forooshyar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completedActions as $action): 
                    $typeLabel = $actionTypeLabels[$action['action_type']] ?? $action['action_type'];
                    $statusLabel = $statusLabels[$action['status']] ?? $action['status'];
                ?>
                <tr>
                    <td><?php echo esc_html($typeLabel); ?></td>
                    <td><span class="action-status status-<?php echo esc_attr($action['status']); ?>"><?php echo esc_html($statusLabel); ?></span></td>
                    <td><?php echo esc_html($action['priority_score']); ?></td>
                    <td><?php echo esc_html($action['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Activity Chart -->
    <div class="aiagent-section">
        <h3><span class="dashicons dashicons-chart-area"></span> <?php _e('فعالیت ۳۰ روز گذشته', 'forooshyar'); ?></h3>
        <canvas id="activity-chart" height="100"></canvas>
    </div>
</div>
