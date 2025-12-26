/**
 * AI Agent Admin JavaScript
 * اسکریپت مدیریت دستیار هوشمند فروش
 * 
 * معماری جدید با Action Scheduler:
 * - پردازش در پس‌زمینه توسط Action Scheduler انجام می‌شود
 * - فقط یک درخواست polling سبک برای نمایش پیشرفت
 * - نیازی به باز بودن مرورگر نیست
 */
(function($) {
    'use strict';

    var progressPollInterval = null;
    var activityChart = null;
    var heartbeatEnabled = false;

    // Initialize when document is ready
    $(document).ready(function() {
        initTabs();
        initActions();
        initDashboard();
        initActivityChart();
        initHeartbeat();
    });

    /**
     * Initialize WordPress Heartbeat API for job monitoring
     */
    function initHeartbeat() {
        $(document).on('heartbeat-send', function(e, data) {
            if (heartbeatEnabled) {
                data.aiagent_check_job = true;
            }
        });

        $(document).on('heartbeat-tick', function(e, data) {
            if (data.aiagent_job_status) {
                updateProgressUI(data.aiagent_job_status);
                
                var status = data.aiagent_job_status.status;
                if (status === 'completed' || status === 'failed' || status === 'cancelled') {
                    heartbeatEnabled = false;
                    handleJobCompletion(data.aiagent_job_status);
                }
            }
        });
    }

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
        $('#run-analysis').on('click', function() {
            startAnalysis();
        });

        $('#cancel-analysis').on('click', function() {
            cancelAnalysis();
        });

        checkOngoingAnalysis();
    }

    /**
     * Initialize action buttons
     */
    function initActions() {
        $(document).on('click', '.btn-execute-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            if (!confirm('آیا از اجرای این اقدام اطمینان دارید؟')) return;
            executeAction(actionId, $(this));
        });

        $(document).on('click', '.btn-approve-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            if (!confirm('آیا از تأیید این اقدام اطمینان دارید؟')) return;
            approveAction(actionId, $(this));
        });

        $(document).on('click', '.execute-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            if (!confirm(aiagentAdmin.strings.confirm)) return;
            executeAction(actionId, $(this));
        });

        $(document).on('click', '.approve-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            if (!confirm(aiagentAdmin.strings.confirm)) return;
            approveAction(actionId, $(this));
        });
    }

    /**
     * Start async analysis (using Action Scheduler)
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
                    
                    heartbeatEnabled = true;
                    
                    // شروع polling برای نمایش پیشرفت
                    // با Action Scheduler، پردازش در پس‌زمینه انجام می‌شود
                    // و نیازی به باز بودن مرورگر نیست
                    startProgressPolling();
                    
                    if (typeof wp !== 'undefined' && wp.heartbeat) {
                        wp.heartbeat.interval('fast');
                    }
                    
                    showNotice('success', 'تحلیل شروع شد. پردازش در پس‌زمینه انجام می‌شود و می‌توانید این صفحه را ببندید.');
                } else {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> شروع تحلیل');
                    showNotice('error', response.data.message || response.data.error || 'خطا در شروع تحلیل');
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
                heartbeatEnabled = false;
                stopProgressPolling();
                resetAnalysisUI();
                
                if (typeof wp !== 'undefined' && wp.heartbeat) {
                    wp.heartbeat.interval('standard');
                }
                
                if (response.success) {
                    showNotice('success', 'تحلیل لغو شد');
                } else {
                    showNotice('error', response.data.message || 'خطا در لغو تحلیل');
                }
            },
            error: function() {
                heartbeatEnabled = false;
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
                if (!response.success) return;
                
                var status = response.data.status;
                console.log('[AIAgent] وضعیت فعلی کار:', status, response.data);
                
                // اگر کار تمام شده، لغو شده یا شکست خورده، وضعیت را پاک کن
                if (status === 'completed' || status === 'failed' || status === 'cancelled') {
                    // تأیید اتمام
                    $.ajax({
                        url: aiagentAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aiagent_acknowledge_completion',
                            nonce: aiagentAdmin.nonce
                        }
                    });
                    return;
                }
                
                // اگر کار در حال اجرا یا لغو است
                if (status === 'running' || status === 'cancelling') {
                    console.log('[AIAgent] کار در حال اجرا یافت شد');
                    $('#analysis-progress-section').show();
                    $('#cancel-analysis').show();
                    $('#run-analysis').hide();
                    updateProgressUI(response.data);
                    
                    heartbeatEnabled = true;
                    startProgressPolling();
                    
                    if (typeof wp !== 'undefined' && wp.heartbeat) {
                        wp.heartbeat.interval('fast');
                    }
                    
                    // نمایش پیام که پردازش در پس‌زمینه انجام می‌شود
                    showNotice('info', 'تحلیل در پس‌زمینه در حال انجام است. می‌توانید این صفحه را ببندید.');
                }
            }
        });
    }

    /**
     * Start polling for progress updates
     * با Action Scheduler، این فقط برای نمایش پیشرفت است
     */
    function startProgressPolling() {
        if (progressPollInterval) {
            clearInterval(progressPollInterval);
        }

        // نظرسنجی هر 3 ثانیه
        progressPollInterval = setInterval(function() {
            $.ajax({
                url: aiagentAdmin.ajaxUrl,
                type: 'POST',
                timeout: 10000,
                data: {
                    action: 'aiagent_get_analysis_progress',
                    nonce: aiagentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateProgressUI(response.data);

                        if (response.data.status === 'completed' || response.data.status === 'failed' || response.data.status === 'cancelled') {
                            handleJobCompletion(response.data);
                        }
                    }
                }
            });
        }, 3000);
    }

    /**
     * Handle job completion
     */
    function handleJobCompletion(data) {
        heartbeatEnabled = false;
        stopProgressPolling();
        
        if (typeof wp !== 'undefined' && wp.heartbeat) {
            wp.heartbeat.interval('standard');
        }
        
        // تأیید اتمام و پاکسازی وضعیت در سرور
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_acknowledge_completion',
                nonce: aiagentAdmin.nonce
            }
        });
        
        if (data.status === 'completed') {
            showNotice('success', 'تحلیل با موفقیت انجام شد. محصولات: ' + data.products_analyzed + '/' + data.products_total + '، اقدامات: ' + data.actions_created);
            setTimeout(function() {
                location.reload();
            }, 2000);
        } else if (data.status === 'failed') {
            showNotice('error', 'تحلیل با خطا مواجه شد');
            resetAnalysisUI();
        } else if (data.status === 'cancelled') {
            showNotice('info', 'تحلیل لغو شد');
            resetAnalysisUI();
        }
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
        } else {
            $('#current-item').text('');
        }
        
        // نمایش تعداد کارهای در صف (Action Scheduler)
        if (data.pending_actions !== undefined && data.pending_actions > 0) {
            $('#pending-queue').text('در صف: ' + data.pending_actions + ' مورد');
        } else {
            $('#pending-queue').text('');
        }

        var statusText = 'در حال تحلیل...';
        if (data.status === 'completed') {
            statusText = 'تحلیل کامل شد';
        } else if (data.status === 'failed') {
            statusText = 'خطا در تحلیل';
        } else if (data.status === 'cancelled') {
            statusText = 'تحلیل لغو شد';
        } else if (data.is_cancelling) {
            statusText = 'در حال لغو...';
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
        $('#pending-queue').text('');
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
                    setTimeout(function() { location.reload(); }, 1500);
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
                    setTimeout(function() { location.reload(); }, 1500);
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
        if ($canvas.length === 0 || typeof Chart === 'undefined') return;

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
        var labels = [], analysesData = [], actionsData = [];

        for (var i = 0; i < dailyData.length; i++) {
            labels.push(dailyData[i].date);
            analysesData.push(dailyData[i].analyses || 0);
            actionsData.push(dailyData[i].actions || 0);
        }

        if (activityChart) activityChart.destroy();

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
                plugins: { legend: { position: 'top', rtl: true } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        $('.aiagent-notice-temp').remove();
        var noticeType = type === 'warning' ? 'warning' : type;
        var $notice = $('<div class="notice notice-' + noticeType + ' is-dismissible aiagent-notice-temp"><p>' + message + '</p></div>');
        $('.wrap h1').first().after($notice);
        if (type !== 'warning' && type !== 'info') {
            setTimeout(function() { $notice.fadeOut(function() { $(this).remove(); }); }, 5000);
        }
    }

})(jQuery);

// Add spin animation
var style = document.createElement('style');
style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } .dashicons.spin { animation: spin 1s linear infinite; }';
document.head.appendChild(style);
