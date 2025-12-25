<?php
/**
 * AI Agent Dashboard View
 * Persian interface for AI Sales Agent dashboard
 * 
 * @var array $stats
 * @var array $status
 */

if (!defined('ABSPATH')) exit;

$tierNames = [
    'free' => 'رایگان',
    'basic' => 'پایه',
    'pro' => 'حرفه‌ای',
    'enterprise' => 'سازمانی',
];
$currentTierName = isset($tierNames[$status['tier']]) ? $tierNames[$status['tier']] : $status['tier'];
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
        <button type="button" class="button button-primary" id="run-analysis" <?php echo !$status['enabled'] ? 'disabled' : ''; ?>>
            <?php _e('اجرای تحلیل', 'forooshyar'); ?>
        </button>
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
                $subTierName = isset($tierNames[$stats['subscription']['tier']]) 
                    ? $tierNames[$stats['subscription']['tier']] 
                    : $stats['subscription']['tier'];
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

    <!-- Loading Overlay -->
    <div id="aiagent-loading" class="aiagent-loading" style="display: none;">
        <div class="spinner"></div>
        <span><?php _e('در حال اجرای تحلیل...', 'forooshyar'); ?></span>
    </div>
</div>

<style>
.aiagent-dashboard {
    font-family: 'Vazir', 'Tahoma', sans-serif;
}

.aiagent-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.aiagent-status {
    display: flex;
    gap: 10px;
    align-items: center;
}

.status-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 13px;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

.tier-badge {
    padding: 5px 15px;
    border-radius: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: bold;
    font-size: 13px;
}

.aiagent-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 40px;
    color: #2271b1;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
}

.stat-label {
    color: #646970;
    font-size: 13px;
}

.aiagent-subscription-box,
.aiagent-chart-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.aiagent-subscription-box h3,
.aiagent-chart-box h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.usage-bars {
    margin-top: 15px;
}

.usage-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.usage-item > span:first-child {
    min-width: 120px;
}

.usage-bar {
    flex: 1;
    height: 20px;
    background: #f0f0f1;
    border-radius: 10px;
    overflow: hidden;
}

.usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.aiagent-loading {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.aiagent-loading .spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2271b1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Run Analysis Button
    $('#run-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#aiagent-loading').show();

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_run_analysis',
                nonce: aiagentAdmin.nonce,
                type: 'all'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('تحلیل با موفقیت انجام شد', 'forooshyar'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا در اجرای تحلیل', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $('#aiagent-loading').hide();
            }
        });
    });

    // Activity Chart
    if (typeof Chart !== 'undefined') {
        var ctx = document.getElementById('activity-chart').getContext('2d');
        var chartData = <?php echo wp_json_encode($stats['analysis_daily']); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(function(item) { return item.date; }),
                datasets: [{
                    label: '<?php _e('تحلیل‌ها', 'forooshyar'); ?>',
                    data: chartData.map(function(item) { return item.count; }),
                    borderColor: '#2271b1',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>
