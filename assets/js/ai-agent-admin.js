/**
 * AI Agent Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initTabs();
        initActions();
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
     * Initialize action buttons
     */
    function initActions() {
        // Execute action button
        $(document).on('click', '.execute-action', function(e) {
            e.preventDefault();
            var actionId = $(this).data('id');
            
            if (!confirm(aiagentAdmin.strings.confirm)) {
                return;
            }

            executeAction(actionId, $(this));
        });

        // Approve action button
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
     * Execute an action via AJAX
     */
    function executeAction(actionId, $btn) {
        $btn.prop('disabled', true);

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
                    showNotice('success', aiagentAdmin.strings.success);
                    location.reload();
                } else {
                    showNotice('error', response.data.message || aiagentAdmin.strings.error);
                }
            },
            error: function() {
                showNotice('error', aiagentAdmin.strings.error);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Approve an action via AJAX
     */
    function approveAction(actionId, $btn) {
        $btn.prop('disabled', true);

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
                    showNotice('success', aiagentAdmin.strings.success);
                    location.reload();
                } else {
                    showNotice('error', response.data.message || aiagentAdmin.strings.error);
                }
            },
            error: function() {
                showNotice('error', aiagentAdmin.strings.error);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery);
