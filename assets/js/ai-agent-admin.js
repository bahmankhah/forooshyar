/**
 * AI Agent Admin JavaScript
 */
(function($) {
    'use strict';

    var progressPollInterval = null;
    var activityChart = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initTabs();
        initActions();
        initDashboard();
        initActivityChart();
    });

    /**
     * Initialize tab navigation
     */
    function initTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.settings-section').hide();
            $(target).show();
        });
    }

    /**
     * Initialize dashboard functionality
     */
    function initDashboard() {
        // Start analysis button
        $('#run-analysis').on('click', function() {
            startAnalysis();
        });

        // Cancel analysis button
        $('#cancel-analysis').on('click', function() {
            cancelAnalysis();
        });

        // Check if there's an ongoing analysis
        checkOngoingAnalysis();
    }

    /**
     * Initialize action buttons
     */
    function initActions() {
        // Execute action button (dashboard cards)
        $(document).on('click', '.btn-execute-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            
            if (!confirm('آیا از اجرای این اقدام اطمینان دارید؟')) {
                return;
            }

            executeAction(actionId, $(this));
        });

        // Approve action button (dashboard cards)
        $(document).on('click', '.btn-approve-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            
            if (!confirm('آیا از تأیید این اقدام اطمینان دارید؟')) {
                return;
            }

            approveAction(actionId, $(this));
        });

        // Legacy execute action button
        $(document).on('click', '.execute-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            
            if (!confirm(aiagentAdmin.strings.confirm)) {
                return;
            }

            executeAction(actionId, $(this));
        });

        // Legacy approve action button
        $(document).on('click', '.approve-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            
            if (!confirm(aiagentAdmin.strings.confirm)) {
                return;
            }

            approveAction(actionId, $(this));
        });
    }

    /**
     * Start async analysis
     */
    function startAnalysis() {
        var $btn = $('#run-analysis');
        var $cancelBtn = $('#cancel-analysis');
        var $progressSection = $('#analysis-progress-section');

        $btn.prop('disabled', true).text('در حال شروع...');

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_start_analysis',
                nonce: aiagentAdmin.nonce,
                type: 'all'
            },
            success: function(response) {
                if (response.success) {
                    $progressSection.show();
                    $cancelBtn.show();
                    $btn.hide();
                    startProgressPolling();
                    showNotice('success', 'تحلیل شروع شد');
                } else {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> شروع تحلیل');
                    showNotice('error', response.data.message || 'خطا در شروع تحلیل');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> شروع تحلیل');
                showNotice('error', 'خطا در ارتباط با سرور');
            }
        });
    }

    /**
     * Cancel running analysis
     */
    function cancelAnalysis() {
        var $cancelBtn = $('#cancel-analysis');

        $cancelBtn.prop('disabled', true).text('در حال لغو...');

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_cancel_analysis',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                stopProgressPolling();
                resetAnalysisUI();
                if (response.success) {
                    showNotice('success', 'تحلیل لغو شد');
                } else {
                    showNotice('error', response.data.message || 'خطا در لغو تحلیل');
                }
            },
            error: function() {
                stopProgressPolling();
                resetAnalysisUI();
                showNotice('error', 'خطا در ارتباط با سرور');
            }
        });
    }

    /**
     * Check for ongoing analysis on page load
     */
    function checkOngoingAnalysis() {
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_get_analysis_progress',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.status === 'running') {
                    $('#analysis-progress-section').show();
                    $('#cancel-analysis').show();
                    $('#run-analysis').hide();
                    updateProgressUI(response.data);
                    startProgressPolling();
                }
            }
        });
    }

    /**
     * Start polling for progress updates
     */
    function startProgressPolling() {
        if (progressPollInterval) {
            clearInterval(progressPollInterval);
        }

        progressPollInterval = setInterval(function() {
            $.ajax({
                url: aiagentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiagent_get_analysis_progress',
                    nonce: aiagentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateProgressUI(response.data);

                        if (response.data.status === 'completed' || response.data.status === 'failed' || response.data.status === 'cancelled') {
                            stopProgressPolling();
                            
                            if (response.data.status === 'completed') {
                                showNotice('success', 'تحلیل با موفقیت انجام شد. محصولات: ' + response.data.products_analyzed + '/' + response.data.products_total + '، اقدامات: ' + response.data.actions_created);
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else if (response.data.status === 'failed') {
                                showNotice('error', 'تحلیل با خطا مواجه شد: ' + (response.data.error || 'خطای ناشناخته'));
                                resetAnalysisUI();
                            } else {
                                resetAnalysisUI();
                            }
                        }
                    }
                }
            });
        }, 2000);
    }

    /**
     * Stop progress polling
     */
    function stopProgressPolling() {
        if (progressPollInterval) {
            clearInterval(progressPollInterval);
            progressPollInterval = null;
        }
    }

    /**
     * Update progress UI
     */
    function updateProgressUI(data) {
        var percent = data.progress || 0;
        
        $('.progress-percent').text(percent + '%');
        $('.progress-bar-fill').css('width', percent + '%');
        
        $('#progress-products').text('محصولات: ' + (data.products_analyzed || 0) + '/' + (data.products_total || 0));
        $('#progress-customers').text('مشتریان: ' + (data.customers_analyzed || 0) + '/' + (data.customers_total || 0));
        $('#progress-actions').text('اقدامات: ' + (data.actions_created || 0));
        
        if (data.current_item) {
            $('#current-item').text('در حال تحلیل: ' + data.current_item);
        }

        // Update status text
        var statusText = 'در حال تحلیل...';
        if (data.status === 'completed') {
            statusText = 'تحلیل کامل شد';
        } else if (data.status === 'failed') {
            statusText = 'خطا در تحلیل';
        } else if (data.status === 'cancelled') {
            statusText = 'تحلیل لغو شد';
        }
        $('.progress-status').text(statusText);
    }

    /**
     * Reset analysis UI to initial state
     */
    function resetAnalysisUI() {
        $('#analysis-progress-section').hide();
        $('#cancel-analysis').hide().prop('disabled', false).text('لغو تحلیل');
        $('#run-analysis').show().prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> شروع تحلیل');
        
        $('.progress-percent').text('0%');
        $('.progress-bar-fill').css('width', '0%');
        $('#progress-products').text('محصولات: 0/0');
        $('#progress-customers').text('مشتریان: 0/0');
        $('#progress-actions').text('اقدامات: 0');
        $('#current-item').text('');
        $('.progress-status').text('در حال تحلیل...');
    }

    /**
     * Execute an action via AJAX
     */
    function executeAction(actionId, $btn) {
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_execute_action',
                nonce: aiagentAdmin.nonce,
                action_id: actionId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'اقدام با موفقیت اجرا شد');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message || 'خطا در اجرای اقدام');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotice('error', 'خطا در ارتباط با سرور');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Approve an action via AJAX
     */
    function approveAction(actionId, $btn) {
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_approve_action',
                nonce: aiagentAdmin.nonce,
                action_id: actionId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'اقدام تأیید شد');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message || 'خطا در تأیید اقدام');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showNotice('error', 'خطا در ارتباط با سرور');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Initialize activity chart
     */
    function initActivityChart() {
        var $canvas = $('#activity-chart');
        if ($canvas.length === 0 || typeof Chart === 'undefined') {
            return;
        }

        // Fetch stats for chart
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_get_stats',
                nonce: aiagentAdmin.nonce,
                days: 30
            },
            success: function(response) {
                if (response.success && response.data.daily) {
                    renderActivityChart($canvas[0], response.data.daily);
                }
            }
        });
    }

    /**
     * Render activity chart
     */
    function renderActivityChart(canvas, dailyData) {
        var labels = [];
        var analysesData = [];
        var actionsData = [];

        // Process daily data
        for (var i = 0; i < dailyData.length; i++) {
            var item = dailyData[i];
            labels.push(item.date);
            analysesData.push(item.analyses || 0);
            actionsData.push(item.actions || 0);
        }

        if (activityChart) {
            activityChart.destroy();
        }

        activityChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'تحلیل‌ها',
                        data: analysesData,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'اقدامات',
                        data: actionsData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        // Remove existing notices
        $('.aiagent-notice-temp').remove();
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible aiagent-notice-temp"><p>' + message + '</p></div>');
        $('.wrap h1').first().after($notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery);

// Add spin animation for loading icons
var style = document.createElement('style');
style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } .dashicons.spin { animation: spin 1s linear infinite; }';
document.head.appendChild(style);
