<?php
/**
 * AI Agent Dashboard View
 * 
 * @var array $stats
 * @var array $status
 */

if (!defined('ABSPATH')) exit;
?>
<div class="wrap aiagent-dashboard">
    <h1><?php _e('AI Sales Agent Dashboard', 'forooshyar'); ?></h1>

    <?php if (!$status['enabled']): ?>
    <div class="notice notice-warning">
        <p><?php _e('AI Agent module is currently disabled. Enable it in settings to start using.', 'forooshyar'); ?></p>
    </div>
    <?php endif; ?>

    <div class="aiagent-header">
        <div class="aiagent-status">
            <span class="status-badge <?php echo $status['enabled'] ? 'active' : 'inactive'; ?>">
                <?php echo $status['enabled'] ? __('Active', 'forooshyar') : __('Inactive', 'forooshyar'); ?>
            </span>
            <span class="tier-badge"><?php echo esc_html(ucfirst($status['tier'])); ?></span>
        </div>
        <button type="button" class="button button-primary" id="run-analysis" <?php echo !$status['enabled'] ? 'disabled' : ''; ?>>
            <?php _e('Run Analysis', 'forooshyar'); ?>
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="aiagent-stats-grid">
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-clock"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['pending_actions']); ?></span>
                <span class="stat-label"><?php _e('Pending Actions', 'forooshyar'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-yes-alt"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['completed_today']); ?></span>
                <span class="stat-label"><?php _e('Completed Today', 'forooshyar'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-chart-line"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['success_rate']); ?>%</span>
                <span class="stat-label"><?php _e('Success Rate', 'forooshyar'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-search"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['summary']['analyses_today']); ?></span>
                <span class="stat-label"><?php _e("Today's Analyses", 'forooshyar'); ?></span>
            </div>
        </div>
    </div>

    <!-- Subscription Status -->
    <div class="aiagent-subscription-box">
        <h3><?php _e('Subscription Status', 'forooshyar'); ?></h3>
        <div class="subscription-info">
            <p>
                <strong><?php _e('Current Tier:', 'forooshyar'); ?></strong>
                <?php echo esc_html(ucfirst($stats['subscription']['tier'])); ?>
            </p>
            <div class="usage-bars">
                <div class="usage-item">
                    <span><?php _e('Analyses Today:', 'forooshyar'); ?></span>
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
                    <span><?php _e('Actions Today:', 'forooshyar'); ?></span>
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
        <h3><?php _e('Analysis Activity (Last 30 Days)', 'forooshyar'); ?></h3>
        <canvas id="activity-chart" height="100"></canvas>
    </div>

    <!-- Loading Overlay -->
    <div id="aiagent-loading" class="aiagent-loading" style="display: none;">
        <div class="spinner"></div>
        <span><?php _e('Running analysis...', 'forooshyar'); ?></span>
    </div>
</div>

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
                    alert(aiagentAdmin.strings.success);
                    location.reload();
                } else {
                    alert(response.data.message || aiagentAdmin.strings.error);
                }
            },
            error: function() {
                alert(aiagentAdmin.strings.error);
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
                    label: '<?php _e('Analyses', 'forooshyar'); ?>',
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
