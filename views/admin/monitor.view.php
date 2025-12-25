<?php
/**
 * API Monitor Page Template
 * Persian interface for monitoring API responses
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap forooshyar-admin forooshyar-monitor" dir="rtl">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <div class="forooshyar-admin-header">
        <div class="forooshyar-logo">
            <h2><?php _e('مانیتور API فروشیار', 'forooshyar'); ?></h2>
            <p><?php _e('مشاهده و تست پاسخ‌های API در زمان واقعی', 'forooshyar'); ?></p>
            <div class="forooshyar-header-meta">
                <span class="forooshyar-current-date" data-tooltip-fa="<?php _e('تاریخ شمسی امروز', 'forooshyar'); ?>"></span>
                <span class="forooshyar-current-time" data-tooltip-fa="<?php _e('ساعت فعلی', 'forooshyar'); ?>"></span>
                <span class="forooshyar-api-status" data-tooltip-fa="<?php _e('وضعیت API', 'forooshyar'); ?>">
                    <span class="forooshyar-status-indicator forooshyar-status-active"></span>
                    <?php _e('فعال', 'forooshyar'); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="forooshyar-monitor-container">
        <!-- API Testing Section -->
        <div class="forooshyar-monitor-section">
            <h3><?php _e('تست API', 'forooshyar'); ?></h3>
            
            <div class="forooshyar-api-tester">
                <div class="forooshyar-api-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api-endpoint"><?php _e('نقطه پایانی', 'forooshyar'); ?></label>
                            </th>
                            <td>
                                <select id="api-endpoint" class="regular-text">
                                    <?php foreach ($api_endpoints as $key => $endpoint): ?>
                                        <option value="<?php echo esc_attr($key); ?>" 
                                                data-url="<?php echo esc_attr($endpoint['url']); ?>"
                                                data-method="<?php echo esc_attr($endpoint['method']); ?>"
                                                data-params='<?php echo esc_attr(wp_json_encode($endpoint['params'])); ?>'>
                                            <?php echo esc_html($endpoint['url']); ?> (<?php echo esc_html($endpoint['method']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-params"><?php _e('پارامترها', 'forooshyar'); ?></label>
                            </th>
                            <td>
                                <div id="api-params-container">
                                    <!-- Dynamic parameter inputs will be generated here -->
                                </div>
                                <p class="description">
                                    <?php _e('پارامترهای مورد نیاز بر اساس نقطه پایانی انتخابی نمایش داده می‌شوند', 'forooshyar'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="test-api-btn" class="button button-primary">
                            <?php _e('تست API', 'forooshyar'); ?>
                        </button>
                        <span id="api-loading" class="spinner" style="display: none;"></span>
                    </p>
                </div>
                
                <div class="forooshyar-api-response">
                    <h4><?php _e('پاسخ API', 'forooshyar'); ?></h4>
                    <div class="forooshyar-response-meta">
                        <span id="response-time" class="forooshyar-meta-item"></span>
                        <span id="cache-status" class="forooshyar-meta-item"></span>
                        <span id="response-size" class="forooshyar-meta-item"></span>
                        <span id="response-status" class="forooshyar-meta-item"></span>
                    </div>
                    <div class="forooshyar-json-viewer">
                        <button class="forooshyar-copy-btn" onclick="copyJsonResponse()"><?php _e('کپی', 'forooshyar'); ?></button>
                        <pre id="api-response-content"></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Statistics Section -->
        <div class="forooshyar-monitor-section">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><?php _e('آمار زنده', 'forooshyar'); ?></h3>
                <button type="button" id="refresh-stats-btn" class="button">
                    <?php _e('بروزرسانی آمار', 'forooshyar'); ?>
                </button>
            </div>
            
            <div class="forooshyar-stats-grid">
                <div class="forooshyar-stat-card">
                    <h4><?php _e('کل درخواست‌ها', 'forooshyar'); ?></h4>
                    <span class="forooshyar-stat-number" id="total-requests">-</span>
                </div>
                <div class="forooshyar-stat-card">
                    <h4><?php _e('نرخ موفقیت کش', 'forooshyar'); ?></h4>
                    <span class="forooshyar-stat-number" id="cache-hit-rate">-</span>
                </div>
                <div class="forooshyar-stat-card">
                    <h4><?php _e('میانگین زمان پاسخ', 'forooshyar'); ?></h4>
                    <span class="forooshyar-stat-number" id="avg-response-time">-</span>
                </div>
                <div class="forooshyar-stat-card">
                    <h4><?php _e('درخواست‌های امروز', 'forooshyar'); ?></h4>
                    <span class="forooshyar-stat-number" id="today-requests">-</span>
                </div>
                <div class="forooshyar-stat-card">
                    <h4><?php _e('کل محصولات', 'forooshyar'); ?></h4>
                    <span class="forooshyar-stat-number" id="total-products">-</span>
                </div>
                <div class="forooshyar-stat-card">
                    <h4><?php _e('ورودی‌های کش', 'forooshyar'); ?></h4>
                    <span class="forooshyar-stat-number" id="cache-entries">-</span>
                </div>
            </div>
        </div>

        <!-- Recent Logs Section -->
        <div class="forooshyar-monitor-section">
            <h3><?php _e('لاگ‌های اخیر', 'forooshyar'); ?></h3>
            
            <div class="forooshyar-logs-container">
                <div class="forooshyar-logs-controls">
                    <button type="button" id="refresh-logs-btn" class="button">
                        <?php _e('بروزرسانی', 'forooshyar'); ?>
                    </button>
                    <button type="button" id="clear-logs-btn" class="button">
                        <?php _e('پاک کردن لاگ‌ها', 'forooshyar'); ?>
                    </button>
                    <select id="logs-filter" class="regular-text">
                        <option value="all"><?php _e('همه درخواست‌ها', 'forooshyar'); ?></option>
                        <option value="success"><?php _e('موفق', 'forooshyar'); ?></option>
                        <option value="error"><?php _e('خطا', 'forooshyar'); ?></option>
                    </select>
                </div>
                
                <table class="wp-list-table widefat fixed striped" id="forooshyar-logs-table">
                    <thead>
                        <tr>
                            <th><?php _e('زمان', 'forooshyar'); ?></th>
                            <th><?php _e('نقطه پایانی', 'forooshyar'); ?></th>
                            <th><?php _e('IP', 'forooshyar'); ?></th>
                            <th><?php _e('زمان پاسخ', 'forooshyar'); ?></th>
                            <th><?php _e('وضعیت', 'forooshyar'); ?></th>
                            <th><?php _e('کش', 'forooshyar'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="forooshyar-logs-tbody">
                        <tr>
                            <td colspan="6" class="forooshyar-no-logs">
                                <?php _e('هیچ لاگی یافت نشد', 'forooshyar'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="forooshyar-logs-pagination">
                    <button type="button" id="logs-prev-btn" class="button" disabled>
                        <?php _e('قبلی', 'forooshyar'); ?>
                    </button>
                    <span id="logs-page-info">صفحه 1 از 1</span>
                    <button type="button" id="logs-next-btn" class="button" disabled>
                        <?php _e('بعدی', 'forooshyar'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.forooshyar-monitor {
    font-family: 'Vazir', 'Tahoma', sans-serif;
}

.forooshyar-monitor-container {
    margin-top: 20px;
}

.forooshyar-monitor-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.forooshyar-monitor-section h3 {
    margin-top: 0;
    color: #1e73be;
    border-bottom: 2px solid #f0f0f1;
    padding-bottom: 10px;
}

.forooshyar-api-tester {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.forooshyar-api-form {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 15px;
}

.forooshyar-api-response {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 15px;
}

.forooshyar-response-meta {
    margin-bottom: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.forooshyar-meta-item {
    display: inline-block;
    margin-left: 15px;
    padding: 5px 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 12px;
}

.forooshyar-json-viewer {
    background: #2d3748;
    color: #e2e8f0;
    padding: 15px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.5;
    max-height: 400px;
    overflow: auto;
    white-space: pre;
    position: relative;
    direction: ltr;
    text-align: left;
}

.forooshyar-json-viewer .json-key {
    color: #63b3ed;
}

.forooshyar-json-viewer .json-string {
    color: #68d391;
}

.forooshyar-json-viewer .json-number {
    color: #fbb6ce;
}

.forooshyar-json-viewer .json-boolean {
    color: #f6ad55;
}

.forooshyar-json-viewer .json-null {
    color: #a0aec0;
}

.forooshyar-copy-btn {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #4a5568;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 11px;
}

.forooshyar-copy-btn:hover {
    background: #2d3748;
}

.forooshyar-meta-item.success {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.forooshyar-meta-item.error {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.status-success {
    color: #28a745;
    font-weight: bold;
}

.status-error {
    color: #dc3545;
    font-weight: bold;
}

.forooshyar-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.forooshyar-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.forooshyar-stat-card h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    opacity: 0.9;
}

.forooshyar-stat-number {
    font-size: 24px;
    font-weight: bold;
    display: block;
}

.forooshyar-logs-container {
    margin-top: 20px;
}

.forooshyar-logs-controls {
    margin-bottom: 15px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.forooshyar-logs-pagination {
    margin-top: 15px;
    text-align: center;
}

.forooshyar-logs-pagination button {
    margin: 0 10px;
}

.forooshyar-no-logs {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

#api-params-container {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: #f9f9f9;
    min-height: 50px;
}

.forooshyar-param-input {
    margin-bottom: 10px;
}

.forooshyar-param-input label {
    display: inline-block;
    width: 120px;
    font-weight: bold;
}

.forooshyar-param-input input[type="text"],
.forooshyar-param-input input[type="number"] {
    width: 200px;
    margin-right: 10px;
}

.forooshyar-param-input input[type="checkbox"].forooshyar-checkbox {
    width: 18px;
    height: 18px;
    margin: 0;
    vertical-align: middle;
}

.forooshyar-param-input .description {
    margin-top: 5px;
    margin-right: 125px;
    color: #666;
    font-size: 12px;
}

.forooshyar-header-meta {
    margin-top: 15px;
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.forooshyar-header-meta span {
    background: rgba(255, 255, 255, 0.2);
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    gap: 6px;
}

.forooshyar-status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.forooshyar-status-active {
    background: #28a745;
    box-shadow: 0 0 6px rgba(40, 167, 69, 0.6);
    animation: forooshyar-pulse 2s infinite;
}

@keyframes forooshyar-pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

@media (max-width: 768px) {
    .forooshyar-api-tester {
        grid-template-columns: 1fr;
    }
    
    .forooshyar-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var logsPerPage = 20;
    
    // Initialize endpoint parameters
    updateEndpointParams();
    
    // Load initial stats and logs
    loadStats();
    loadLogs();
    
    // Auto-refresh stats every 30 seconds
    setInterval(loadStats, 30000);
    
    // Handle endpoint change
    $('#api-endpoint').on('change', function() {
        updateEndpointParams();
    });
    
    // Handle API test
    $('#test-api-btn').on('click', function() {
        testApi();
    });
    
    // Handle logs refresh
    $('#refresh-logs-btn').on('click', function() {
        loadLogs();
    });
    
    // Handle stats refresh
    $('#refresh-stats-btn').on('click', function() {
        loadStats();
    });
    
    // Handle logs filter
    $('#logs-filter').on('change', function() {
        currentPage = 1;
        loadLogs();
    });
    
    // Handle pagination
    $('#logs-prev-btn').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadLogs();
        }
    });
    
    $('#logs-next-btn').on('click', function() {
        currentPage++;
        loadLogs();
    });
    
    function updateEndpointParams() {
        var selectedOption = $('#api-endpoint option:selected');
        var paramsData = selectedOption.data('params');
        var params = [];
        
        // Handle different data types
        if (typeof paramsData === 'string') {
            try {
                params = JSON.parse(paramsData);
            } catch (e) {
                console.error('Error parsing params:', e);
                params = [];
            }
        } else if (Array.isArray(paramsData)) {
            params = paramsData;
        } else if (paramsData) {
            params = [];
        }
        
        var container = $('#api-params-container');
        
        container.empty();
        
        if (params.length === 0) {
            container.html('<p><?php _e("این نقطه پایانی پارامتری ندارد", "forooshyar"); ?></p>');
            return;
        }
        
        // Persian translations for parameter names
        var paramTranslations = {
            'id': 'شناسه',
            'page': 'صفحه',
            'per_page': 'تعداد در صفحه',
            'limit': 'تعداد',
            'show_variations': 'نمایش تنوعات',
            'ids': 'شناسه‌ها',
            'slugs': 'نام‌ها'
        };
        
        params.forEach(function(param) {
            var inputType = 'text';
            var placeholder = '';
            var description = '';
            var defaultValue = '';
            var labelText = paramTranslations[param] || param;
            
            // Set appropriate input types and placeholders based on parameter name
            switch(param) {
                case 'id':
                    inputType = 'number';
                    placeholder = 'مثال: ۱۲۳';
                    description = 'شناسه عددی محصول';
                    break;
                case 'page':
                    inputType = 'number';
                    placeholder = 'مثال: ۱';
                    description = 'شماره صفحه (پیش‌فرض: ۱)';
                    defaultValue = '1';
                    break;
                case 'per_page':
                case 'limit':
                    inputType = 'number';
                    placeholder = 'مثال: ۱۰';
                    description = 'تعداد محصولات در هر صفحه (حداکثر: ۱۰۰)';
                    defaultValue = '10';
                    break;
                case 'show_variations':
                    inputType = 'checkbox';
                    description = 'نمایش تنوعات محصولات متغیر';
                    break;
                case 'ids':
                    placeholder = 'مثال: ۱,۲,۳,۴';
                    description = 'شناسه‌های محصولات جدا شده با کاما';
                    break;
                case 'slugs':
                    placeholder = 'مثال: product-1,product-2';
                    description = 'نام‌های محصولات جدا شده با کاما';
                    break;
                default:
                    placeholder = 'مقدار ' + param;
            }
            
            var inputHtml = '<div class="forooshyar-param-input">' +
                '<label for="param-' + param + '">' + labelText + ':</label>';
            
            if (inputType === 'checkbox') {
                inputHtml += '<input type="checkbox" id="param-' + param + '" name="' + param + '" class="forooshyar-checkbox" checked>';
            } else {
                inputHtml += '<input type="' + inputType + '" id="param-' + param + '" name="' + param + '" class="regular-text" placeholder="' + placeholder + '" value="' + defaultValue + '">';
            }
            
            if (description) {
                inputHtml += '<p class="description">' + description + '</p>';
            }
            
            inputHtml += '</div>';
            container.append(inputHtml);
        });
    }
    
    function testApi() {
        var endpoint = $('#api-endpoint').val();
        var params = {};
        
        $('#api-params-container input').each(function() {
            var $input = $(this);
            var name = $input.attr('name');
            
            if (name) {
                if ($input.attr('type') === 'checkbox') {
                    params[name] = $input.is(':checked');
                } else {
                    var value = $input.val();
                    if (value) {
                        // Convert numeric strings to numbers for appropriate parameters
                        if (['id', 'page', 'per_page', 'limit'].includes(name)) {
                            params[name] = parseInt(value);
                        } else {
                            params[name] = value;
                        }
                    }
                }
            }
        });
        
        // Map per_page to limit for API compatibility
        if (params.per_page && !params.limit) {
            params.limit = params.per_page;
            delete params.per_page;
        }
        
        $('#api-loading').addClass('is-active');
        $('#test-api-btn').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'forooshyar_test_api',
                endpoint: endpoint,
                params: params,
                nonce: '<?php echo wp_create_nonce("forooshyar_api_test"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayApiResponse(response.data);
                } else {
                    displayApiError(response.data.message);
                }
            },
            error: function() {
                displayApiError('<?php _e("خطا در ارتباط با سرور", "forooshyar"); ?>');
            },
            complete: function() {
                $('#api-loading').removeClass('is-active');
                $('#test-api-btn').prop('disabled', false);
            }
        });
    }
    
    function displayApiResponse(data) {
        $('#response-time').text('<?php _e("زمان پاسخ: ", "forooshyar"); ?>' + data.response_time + ' ms');
        $('#cache-status').text('<?php _e("وضعیت کش: ", "forooshyar"); ?>' + data.cache_status);
        $('#response-status').text('<?php _e("وضعیت: ", "forooshyar"); ?>موفق').removeClass('error').addClass('success');
        
        var responseSize = JSON.stringify(data.response).length;
        $('#response-size').text('<?php _e("اندازه: ", "forooshyar"); ?>' + formatBytes(responseSize));
        
        var formattedJson = syntaxHighlight(JSON.stringify(data.response, null, 2));
        $('#api-response-content').html(formattedJson);
    }
    
    function displayApiError(message) {
        $('#response-time').text('');
        $('#cache-status').text('');
        $('#response-size').text('');
        $('#response-status').text('<?php _e("وضعیت: ", "forooshyar"); ?>خطا').removeClass('success').addClass('error');
        
        var errorResponse = {
            error: true,
            message: message,
            timestamp: new Date().toISOString()
        };
        
        var formattedJson = syntaxHighlight(JSON.stringify(errorResponse, null, 2));
        $('#api-response-content').html(formattedJson);
    }
    
    function loadStats() {
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'forooshyar_get_stats'
            },
            success: function(response) {
                if (response.success) {
                    updateStats(response.data);
                } else {
                    console.error('خطا در دریافت آمار:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('خطا در ارتباط با سرور برای دریافت آمار:', error);
                // Show fallback stats
                updateStats({
                    total_requests: '?',
                    cache_hit_rate: '?',
                    average_response_time: '?',
                    today_requests: '?',
                    total_products: '?',
                    cache_entries: '?'
                });
            }
        });
    }
    
    function updateStats(stats) {
        $('#total-requests').text(stats.total_requests || 0);
        $('#cache-hit-rate').text((stats.cache_hit_rate || 0) + '%');
        $('#avg-response-time').text((stats.average_response_time || 0) + ' ms');
        $('#today-requests').text(stats.today_requests || 0);
        $('#total-products').text(stats.total_products || 0);
        
        // Show cache entries with object cache indicator
        var cacheEntriesText = stats.cache_entries || 0;
        if (stats.using_object_cache) {
            // When using object cache (Redis/Memcached), show hits count instead
            // because transients are not stored in database
            cacheEntriesText = (stats.cache_hits || 0) + ' <?php _e("هیت", "forooshyar"); ?>';
        }
        $('#cache-entries').text(cacheEntriesText);
    }
    
    function loadLogs() {
        var filter = $('#logs-filter').val();
        var filterParams = {
            action: 'forooshyar_get_logs',
            page: currentPage,
            per_page: logsPerPage
        };
        
        // Apply status filter
        if (filter === 'success') {
            filterParams.status_code = 200;
        } else if (filter === 'error') {
            // For errors, we'll filter client-side since we need >= 400
            filterParams.status_code = 0; // Get all and filter
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: filterParams,
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Client-side filter for errors if needed
                    if (filter === 'error' && data.logs) {
                        data.logs = data.logs.filter(function(log) {
                            return log.status_code >= 400;
                        });
                    } else if (filter === 'success' && data.logs) {
                        data.logs = data.logs.filter(function(log) {
                            return log.status_code >= 200 && log.status_code < 400;
                        });
                    }
                    
                    displayLogs(data);
                } else {
                    console.error('خطا در دریافت لاگ‌ها:', response.data);
                    displayLogs({logs: [], total: 0});
                }
            },
            error: function(xhr, status, error) {
                console.error('خطا در ارتباط با سرور برای دریافت لاگ‌ها:', error);
                displayLogs({logs: [], total: 0});
            }
        });
    }
    
    function displayLogs(data) {
        var tbody = $('#forooshyar-logs-tbody');
        tbody.empty();
        
        var logs = data.logs || [];
        var total = data.total || 0;
        
        if (logs.length === 0) {
            tbody.append('<tr><td colspan="6" class="forooshyar-no-logs"><?php _e("هیچ لاگی یافت نشد", "forooshyar"); ?></td></tr>');
            $('#logs-page-info').text('صفحه 1 از 1');
            $('#logs-prev-btn').prop('disabled', true);
            $('#logs-next-btn').prop('disabled', true);
            return;
        }
        
        logs.forEach(function(log) {
            var statusClass = (log.status_code >= 200 && log.status_code < 400) ? 'success' : 'error';
            var statusText = (log.status_code >= 200 && log.status_code < 400) ? 'موفق' : 'خطا';
            var cacheStatus = log.cache_hit ? 'HIT' : 'MISS';
            var responseTime = parseFloat(log.response_time || 0).toFixed(2);
            
            // Format date to Persian/Jalali with time
            var formattedDate = '-';
            if (log.created_at) {
                var date = new Date(log.created_at);
                if (!isNaN(date.getTime())) {
                    // Use JalaliCalendar if available, otherwise format in LTR
                    if (typeof JalaliCalendar !== 'undefined') {
                        formattedDate = JalaliCalendar.format(date, 'Y/m/d') + ' ' + 
                            PersianNumber.toPersian(date.getHours().toString().padStart(2, '0') + ':' + 
                            date.getMinutes().toString().padStart(2, '0') + ':' + 
                            date.getSeconds().toString().padStart(2, '0'));
                    } else {
                        formattedDate = date.toLocaleString('fa-IR');
                    }
                }
            }
            
            var row = '<tr>' +
                '<td dir="ltr" style="text-align: right;">' + formattedDate + '</td>' +
                '<td dir="ltr">' + (log.endpoint || '-') + '</td>' +
                '<td dir="ltr">' + (log.ip_address || '-') + '</td>' +
                '<td dir="ltr">' + responseTime + ' ms</td>' +
                '<td><span class="status-' + statusClass + '">' + statusText + ' (' + log.status_code + ')</span></td>' +
                '<td>' + cacheStatus + '</td>' +
                '</tr>';
            tbody.append(row);
        });
        
        // Update pagination
        var totalPages = Math.ceil(total / logsPerPage) || 1;
        $('#logs-page-info').text('صفحه ' + currentPage + ' از ' + totalPages);
        $('#logs-prev-btn').prop('disabled', currentPage <= 1);
        $('#logs-next-btn').prop('disabled', currentPage >= totalPages);
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function syntaxHighlight(json) {
        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            var cls = 'json-number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'json-key';
                } else {
                    cls = 'json-string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'json-boolean';
            } else if (/null/.test(match)) {
                cls = 'json-null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }
});

function copyJsonResponse() {
    var content = $('#api-response-content').text();
    if (content) {
        navigator.clipboard.writeText(content).then(function() {
            // Show temporary success message
            var btn = $('.forooshyar-copy-btn');
            var originalText = btn.text();
            btn.text('<?php _e("کپی شد!", "forooshyar"); ?>');
            setTimeout(function() {
                btn.text(originalText);
            }, 2000);
        }).catch(function() {
            alert('<?php _e("خطا در کپی کردن", "forooshyar"); ?>');
        });
    }
}
</script>