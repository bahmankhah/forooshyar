/**
 * AI Agent Single Analysis
 * Handles single entity analysis from product/user list row actions
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        initSingleAnalysis();
    });

    /**
     * Initialize single analysis functionality
     */
    function initSingleAnalysis() {
        // Handle click on analyze link
        $(document).on('click', '.aiagent-analyze-single', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var entityType = $link.data('entity-type');
            var entityId = $link.data('entity-id');
            var entityName = $link.data('entity-name');
            
            openAnalysisModal(entityType, entityId, entityName);
        });

        // Close modal on overlay click
        $(document).on('click', '.aiagent-modal-overlay', function(e) {
            if ($(e.target).hasClass('aiagent-modal-overlay')) {
                closeAnalysisModal();
            }
        });

        // Close modal on close button click
        $(document).on('click', '.aiagent-modal-close', function(e) {
            e.preventDefault();
            closeAnalysisModal();
        });

        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAnalysisModal();
            }
        });
    }

    /**
     * Open analysis modal and start analysis
     */
    function openAnalysisModal(entityType, entityId, entityName) {
        var $modal = $('#aiagent-analysis-modal');
        var $content = $('#aiagent-modal-content');
        var $title = $('#aiagent-modal-title');
        
        var typeLabel = entityType === 'product' ? 'محصول' : 'مشتری';
        $title.text(aiagentSingleAnalysis.strings.analysis + ' ' + typeLabel + ': ' + entityName);
        
        // Show loading state
        $content.html(
            '<div class="aiagent-loading">' +
                '<span class="spinner is-active"></span>' +
                '<p>' + aiagentSingleAnalysis.strings.analyzing + '</p>' +
            '</div>'
        );
        
        $modal.addClass('active');
        
        // Make AJAX request
        $.ajax({
            url: aiagentSingleAnalysis.ajaxUrl,
            type: 'POST',
            timeout: 120000, // 2 minutes timeout for LLM
            data: {
                action: 'aiagent_analyze_single',
                nonce: aiagentSingleAnalysis.nonce,
                entity_type: entityType,
                entity_id: entityId
            },
            success: function(response) {
                if (response.success) {
                    renderAnalysisResult(response.data);
                } else {
                    renderError(response.data.message || aiagentSingleAnalysis.strings.error);
                }
            },
            error: function(xhr, status, error) {
                var message = aiagentSingleAnalysis.strings.error;
                if (status === 'timeout') {
                    message = 'زمان درخواست به پایان رسید. لطفاً دوباره تلاش کنید.';
                }
                renderError(message);
            }
        });
    }

    /**
     * Close analysis modal
     */
    function closeAnalysisModal() {
        $('#aiagent-analysis-modal').removeClass('active');
    }

    /**
     * Render analysis result in modal
     */
    function renderAnalysisResult(data) {
        var $content = $('#aiagent-modal-content');
        var html = '';
        
        // Dry run note
        if (data.dry_run) {
            html += '<div class="aiagent-dry-run-note">' + aiagentSingleAnalysis.strings.dryRunNote + '</div>';
        }
        
        // Meta info
        html += '<div class="aiagent-meta">';
        html += '<span><strong>' + aiagentSingleAnalysis.strings.priority + ':</strong> ' + data.priority_score + '</span>';
        html += '<span><strong>' + aiagentSingleAnalysis.strings.duration + ':</strong> ' + data.duration_ms + ' ' + aiagentSingleAnalysis.strings.ms + '</span>';
        html += '</div>';
        
        // Analysis section
        html += '<div class="aiagent-analysis-section">';
        html += '<h4>' + aiagentSingleAnalysis.strings.analysis + '</h4>';
        html += '<div class="aiagent-analysis-content">' + formatAnalysis(data.analysis) + '</div>';
        html += '</div>';
        
        // Suggestions section
        if (data.suggestions && data.suggestions.length > 0) {
            html += '<div class="aiagent-analysis-section">';
            html += '<h4>' + aiagentSingleAnalysis.strings.suggestions + ' (' + data.suggestions.length + ')</h4>';
            html += '<ul class="aiagent-suggestions-list">';
            
            for (var i = 0; i < data.suggestions.length; i++) {
                var suggestion = data.suggestions[i];
                html += '<li>';
                html += '<div class="aiagent-suggestion-type">' + formatSuggestionType(suggestion.type) + '</div>';
                if (suggestion.reasoning) {
                    html += '<div class="aiagent-suggestion-reason">' + suggestion.reasoning + '</div>';
                }
                html += '</li>';
            }
            
            html += '</ul>';
            html += '</div>';
        }
        
        $content.html(html);
    }

    /**
     * Render error message
     */
    function renderError(message) {
        var $content = $('#aiagent-modal-content');
        $content.html('<div class="aiagent-error">' + message + '</div>');
    }

    /**
     * Format analysis text (handle JSON or plain text)
     */
    function formatAnalysis(analysis) {
        if (typeof analysis === 'object') {
            // If it's an object, format it nicely
            return JSON.stringify(analysis, null, 2);
        }
        // Escape HTML and preserve line breaks
        return $('<div>').text(analysis).html().replace(/\n/g, '<br>');
    }

    /**
     * Format suggestion type to Persian
     */
    function formatSuggestionType(type) {
        var types = {
            'send_email': 'ارسال ایمیل',
            'send_sms': 'ارسال پیامک',
            'create_discount': 'ایجاد تخفیف',
            'update_product': 'بروزرسانی محصول',
            'schedule_followup': 'زمان‌بندی پیگیری',
            'schedule_price_change': 'زمان‌بندی تغییر قیمت',
            'add_note': 'افزودن یادداشت',
            'update_stock': 'بروزرسانی موجودی',
            'create_bundle': 'ایجاد بسته',
            'notify_admin': 'اطلاع‌رسانی به مدیر'
        };
        return types[type] || type;
    }

})(jQuery);
