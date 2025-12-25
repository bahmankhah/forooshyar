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

    <!-- Subscription Status -->
    <div class="aiagent-subscription-box">
        <h3><?php _e('وضعیت اشتراک', 'forooshyar'); ?></h3>
        <div class="subscription-info">
            <p>
                <strong><?php _e('پلن فعلی:', 'forooshyar'); ?></strong>
                <?php 
                $subTierName = $tierNames[$stats['subscription']['tier']] ?? $stats['subscription']['tier'];
                echo esc_html($subTierName); 
                ?>
            </p>
            <div class="usage-bars">
                <div class="usage-item">
                    <span><?php _e('تحلیل‌های امروز:', 'forooshyar'); ?></span>
                    <div class="usage-bar">
                        <?php
                        $analysisUsage = $stats['subscription']['usage']['analyses'];
                        $analysisPercent = $analysisUsage['allowed'] > 0 
                            ? min(100, ($analysisUsage['used'] / $analysisUsage['allowed']) * 100) 
                            : 0;
                        ?>
                        <div class="usage-fill" style="width: <?php echo $analysisPercent; ?>%"></div>
                    </div>
                    <span><?php echo $analysisUsage['used']; ?>/<?php echo $analysisUsage['allowed'] == -1 ? '∞' : $analysisUsage['allowed']; ?></span>
                </div>
                <div class="usage-item">
                    <span><?php _e('اقدامات امروز:', 'forooshyar'); ?></span>
                    <div class="usage-bar">
                        <?php
                        $actionUsage = $stats['subscription']['usage']['actions'];
                        $actionPercent = $actionUsage['allowed'] > 0 
                            ? min(100, ($actionUsage['used'] / $actionUsage['allowed']) * 100) 
                            : 0;
                        ?>
                        <div class="usage-fill" style="width: <?php echo $actionPercent; ?>%"></div>
                    </div>
                    <span><?php echo $actionUsage['used']; ?>/<?php echo $actionUsage['allowed'] == -1 ? '∞' : $actionUsage['allowed']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Chart -->
    <div class="aiagent-chart-box">
        <h3><?php _e('فعالیت تحلیل (۳۰ روز گذشته)', 'forooshyar'); ?></h3>
        <canvas id="activity-chart" height="100"></canvas>
    </div>

    <!-- Recent Actions -->
    <div class="aiagent-recent-box">
        <h3><?php _e('اقدامات اخیر', 'forooshyar'); ?></h3>
        <?php if (!empty($dashboardData['recent_actions'])): ?>
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
                <?php 
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
                    'pending' => 'در انتظار',
                    'approved' => 'تأیید شده',
                    'completed' => 'انجام شده',
                    'failed' => 'ناموفق',
                    'cancelled' => 'لغو شده',
                ];
                foreach ($dashboardData['recent_actions'] as $action): 
                    $typeLabel = $actionTypeLabels[$action['action_type']] ?? $action['action_type'];
                    $statusLabel = $statusLabels[$action['status']] ?? $action['status'];
                ?>
                <tr>
                    <td><?php echo esc_html($typeLabel); ?></td>
                    <td>
                        <span class="action-status status-<?php echo esc_attr($action['status']); ?>">
                            <?php echo esc_html($statusLabel); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($action['priority_score']); ?></td>
                    <td><?php echo esc_html($action['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="no-data"><?php _e('هنوز اقدامی ثبت نشده است.', 'forooshyar'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Recent Analyses -->
    <div class="aiagent-recent-box">
        <h3><?php _e('تحلیل‌های اخیر', 'forooshyar'); ?></h3>
        <?php if (!empty($dashboardData['recent_analyses'])): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('نوع', 'forooshyar'); ?></th>
                    <th><?php _e('موجودیت', 'forooshyar'); ?></th>
                    <th><?php _e('امتیاز', 'forooshyar'); ?></th>
                    <th><?php _e('تاریخ', 'forooshyar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dashboardData['recent_analyses'] as $analysis): ?>
                <tr>
                    <td><?php echo esc_html($analysis['analysis_type']); ?></td>
                    <td>
                        <?php 
                        if ($analysis['entity_type'] === 'product') {
                            $product = wc_get_product($analysis['entity_id']);
                            echo $product ? esc_html($product->get_name()) : '#' . $analysis['entity_id'];
                        } else {
                            echo '#' . esc_html($analysis['entity_id']);
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html($analysis['priority_score']); ?></td>
                    <td><?php echo esc_html($analysis['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="no-data"><?php _e('هنوز تحلیلی انجام نشده است.', 'forooshyar'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Loading Overlay -->
    <div id="aiagent-loading" class="aiagent-loading" style="display: none;">
        <div class="spinner"></div>
        <span><?php _e('در حال اجرای تحلیل...', 'forooshyar'); ?></span>
    </div>
</div>

<style>
.aiagent-dashboard { font-family: 'Vazir', 'Tahoma', sans-serif; }
.aiagent-header { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; padding: 15px 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
.aiagent-header-actions { display: flex; gap: 10px; }
.aiagent-status { display: flex; gap: 10px; align-items: center; }
.aiagent-progress-box { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin: 20px 0; }
.progress-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.progress-status { font-weight: bold; color: #2271b1; }
.progress-percent { font-size: 18px; font-weight: bold; color: #1d2327; }
.progress-bar-container { height: 24px; background: #f0f0f1; border-radius: 12px; overflow: hidden; margin-bottom: 15px; }
.progress-bar-fill { height: 100%; background: linear-gradient(90deg, #2271b1, #135e96); border-radius: 12px; transition: width 0.3s ease; }
.progress-details { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 10px; font-size: 13px; color: #646970; }
.progress-current { font-size: 12px; color: #888; min-height: 18px; }
#cancel-analysis { color: #d63638; border-color: #d63638; }
#cancel-analysis:hover { background: #d63638; color: #fff; }
.status-badge { padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 13px; }
.status-badge.active { background: #d4edda; color: #155724; }
.status-badge.inactive { background: #f8d7da; color: #721c24; }
.tier-badge { padding: 5px 15px; border-radius: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; font-size: 13px; }
.aiagent-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
.stat-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 15px; }
.stat-icon { font-size: 40px; color: #2271b1; }
.stat-content { display: flex; flex-direction: column; }
.stat-value { font-size: 28px; font-weight: bold; color: #1d2327; }
.stat-label { color: #646970; font-size: 13px; }
.aiagent-subscription-box, .aiagent-chart-box, .aiagent-recent-box { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin: 20px 0; }
.aiagent-subscription-box h3, .aiagent-chart-box h3, .aiagent-recent-box h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
.aiagent-recent-box table { margin-top: 15px; }
.aiagent-recent-box .no-data { color: #666; font-style: italic; text-align: center; padding: 20px; }
.action-status { padding: 3px 8px; border-radius: 3px; font-size: 12px; }
.action-status.status-pending { background: #fff3cd; color: #856404; }
.action-status.status-approved { background: #cce5ff; color: #004085; }
.action-status.status-completed { background: #d4edda; color: #155724; }
.action-status.status-failed { background: #f8d7da; color: #721c24; }
.action-status.status-cancelled { background: #e2e3e5; color: #383d41; }
.usage-bars { margin-top: 15px; }
.usage-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.usage-item > span:first-child { min-width: 120px; }
.usage-bar { flex: 1; height: 20px; background: #f0f0f1; border-radius: 10px; overflow: hidden; }
.usage-fill { height: 100%; background: linear-gradient(90deg, #2271b1, #135e96); border-radius: 10px; transition: width 0.3s ease; }
.aiagent-loading { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 9999; }
.aiagent-loading .spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<script>
jQuery(document).ready(function($) {
    var analysisPollingInterval = null;
    
    function showRunningState() {
        $('#run-analysis').hide();
        $('#cancel-analysis').show();
        $('#analysis-progress-section').show();
    }
    
    function showIdleState() {
        $('#run-analysis').show();
        $('#cancel-analysis').hide();
        $('#analysis-progress-section').hide();
    }
    
    function updateProgressUI(progress) {
        $('.progress-percent').text(progress.percentage + '%');
        $('.progress-bar-fill').css('width', progress.percentage + '%');
        $('#progress-products').text('<?php _e('محصولات:', 'forooshyar'); ?> ' + progress.products.processed + '/' + progress.products.total);
        $('#progress-customers').text('<?php _e('مشتریان:', 'forooshyar'); ?> ' + progress.customers.processed + '/' + progress.customers.total);
        $('#progress-actions').text('<?php _e('اقدامات:', 'forooshyar'); ?> ' + progress.actions_created);
        if (progress.current_item) {
            var itemType = progress.current_item.type === 'product' ? '<?php _e('محصول', 'forooshyar'); ?>' : '<?php _e('مشتری', 'forooshyar'); ?>';
            $('#current-item').text('<?php _e('در حال تحلیل:', 'forooshyar'); ?> ' + itemType + ' #' + progress.current_item.id);
        } else {
            $('#current-item').text('');
        }
        if (progress.is_cancelling) {
            $('.progress-status').text('<?php _e('در حال لغو...', 'forooshyar'); ?>');
            $('#cancel-analysis').prop('disabled', true);
        } else {
            $('.progress-status').text('<?php _e('در حال تحلیل...', 'forooshyar'); ?>');
        }
    }
    
    function pollAnalysisProgress() {
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'aiagent_get_analysis_progress', nonce: aiagentAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    var progress = response.data;
                    if (progress.is_running || progress.is_cancelling) {
                        updateProgressUI(progress);
                    } else if (progress.status === 'completed') {
                        stopPolling(); showIdleState();
                        alert('<?php _e('تحلیل با موفقیت انجام شد', 'forooshyar'); ?>');
                        location.reload();
                    } else if (progress.status === 'cancelled') {
                        stopPolling(); showIdleState();
                        alert('<?php _e('تحلیل لغو شد', 'forooshyar'); ?>');
                    } else if (progress.status === 'failed') {
                        stopPolling(); showIdleState();
                        alert('<?php _e('تحلیل با خطا مواجه شد', 'forooshyar'); ?>');
                    } else {
                        stopPolling(); showIdleState();
                    }
                }
            }
        });
    }
    
    function startPolling() {
        if (analysisPollingInterval) return;
        analysisPollingInterval = setInterval(pollAnalysisProgress, 2000);
        pollAnalysisProgress();
    }
    
    function stopPolling() {
        if (analysisPollingInterval) { clearInterval(analysisPollingInterval); analysisPollingInterval = null; }
    }
    
    $('#run-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: { action: 'aiagent_start_analysis', nonce: aiagentAdmin.nonce, type: 'all' },
            success: function(response) {
                if (response.success) { showRunningState(); startPolling(); }
                else { alert(response.data.error || response.data.message || '<?php _e('خطا در شروع تحلیل', 'forooshyar'); ?>'); }
            },
            error: function() { alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>'); },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    $('#cancel-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('.progress-status').text('<?php _e('در حال لغو...', 'forooshyar'); ?>');
        $.ajax({ url: aiagentAdmin.ajaxUrl, type: 'POST', data: { action: 'aiagent_cancel_analysis', nonce: aiagentAdmin.nonce } });
    });
    
    $.ajax({
        url: aiagentAdmin.ajaxUrl,
        type: 'POST',
        data: { action: 'aiagent_get_analysis_progress', nonce: aiagentAdmin.nonce },
        success: function(response) {
            if (response.success) {
                var progress = response.data;
                if (progress.is_running || progress.is_cancelling) { showRunningState(); updateProgressUI(progress); startPolling(); }
            }
        }
    });

    if (typeof Chart !== 'undefined') {
        var ctx = document.getElementById('activity-chart').getContext('2d');
        var chartData = <?php echo wp_json_encode($stats['analysis_daily']); ?>;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(function(item) { return item.date; }),
                datasets: [{ label: '<?php _e('تحلیل‌ها', 'forooshyar'); ?>', data: chartData.map(function(item) { return item.count; }), borderColor: '#2271b1', tension: 0.1, fill: false }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    }
});
</script>
