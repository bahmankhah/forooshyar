<?php
/**
 * Advanced Settings Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$advanced_config = $config['advanced'] ?? [];
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="custom_field_mapping"><?php _e('نگاشت فیلدهای سفارشی', 'forooshyar'); ?></label>
        </th>
        <td>
            <textarea id="custom_field_mapping" name="advanced[custom_field_mapping]" 
                      class="large-text code" rows="8" 
                      placeholder='<?php _e('مثال:', 'forooshyar'); ?>&#10;{&#10;  "brand": "_product_brand",&#10;  "warranty": "_warranty_info",&#10;  "specifications": "_product_specs"&#10;}'><?php 
                echo esc_textarea($advanced_config['custom_field_mapping'] ?? ''); 
            ?></textarea>
            <p class="forooshyar-field-description">
                <?php _e('نگاشت فیلدهای سفارشی از متافیلدهای محصول به فیلدهای API. فرمت JSON.', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="database_optimization"><?php _e('بهینه‌سازی پایگاه داده', 'forooshyar'); ?></label>
        </th>
        <td>
            <fieldset>
                <legend class="screen-reader-text">
                    <span><?php _e('تنظیمات بهینه‌سازی پایگاه داده', 'forooshyar'); ?></span>
                </legend>
                
                <label>
                    <input type="checkbox" name="advanced[db_optimization][use_indexes]" value="1" 
                           <?php checked($advanced_config['db_optimization']['use_indexes'] ?? true); ?>>
                    <?php _e('استفاده از ایندکس‌های بهینه', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="advanced[db_optimization][limit_queries]" value="1" 
                           <?php checked($advanced_config['db_optimization']['limit_queries'] ?? true); ?>>
                    <?php _e('محدود کردن تعداد کوئری‌ها', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="advanced[db_optimization][use_prepared_statements]" value="1" 
                           <?php checked($advanced_config['db_optimization']['use_prepared_statements'] ?? true); ?>>
                    <?php _e('استفاده از Prepared Statements', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="advanced[db_optimization][batch_processing]" value="1" 
                           <?php checked($advanced_config['db_optimization']['batch_processing'] ?? false); ?>>
                    <?php _e('پردازش دسته‌ای', 'forooshyar'); ?>
                </label>
            </fieldset>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="memory_limit"><?php _e('محدودیت حافظه (MB)', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="memory_limit" name="advanced[memory_limit]" 
                   value="<?php echo esc_attr($advanced_config['memory_limit'] ?? 256); ?>" 
                   min="128" max="2048" class="small-text">
            <p class="forooshyar-field-description">
                <?php _e('حداکثر حافظه قابل استفاده برای پردازش درخواست‌های API', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="execution_time_limit"><?php _e('محدودیت زمان اجرا (ثانیه)', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="execution_time_limit" name="advanced[execution_time_limit]" 
                   value="<?php echo esc_attr($advanced_config['execution_time_limit'] ?? 60); ?>" 
                   min="30" max="300" class="small-text">
            <p class="forooshyar-field-description">
                <?php _e('حداکثر زمان اجرای اسکریپت برای درخواست‌های API', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="error_reporting"><?php _e('گزارش خطاها', 'forooshyar'); ?></label>
        </th>
        <td>
            <select id="error_reporting" name="advanced[error_reporting]" class="regular-text">
                <option value="none" <?php selected($advanced_config['error_reporting'] ?? 'basic', 'none'); ?>>
                    <?php _e('هیچ', 'forooshyar'); ?>
                </option>
                <option value="basic" <?php selected($advanced_config['error_reporting'] ?? 'basic', 'basic'); ?>>
                    <?php _e('پایه', 'forooshyar'); ?>
                </option>
                <option value="detailed" <?php selected($advanced_config['error_reporting'] ?? 'basic', 'detailed'); ?>>
                    <?php _e('تفصیلی', 'forooshyar'); ?>
                </option>
                <option value="debug" <?php selected($advanced_config['error_reporting'] ?? 'basic', 'debug'); ?>>
                    <?php _e('اشکال‌زدایی', 'forooshyar'); ?>
                </option>
            </select>
            <p class="forooshyar-field-description">
                <?php _e('سطح جزئیات گزارش خطاها در لاگ‌ها', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="log_retention"><?php _e('مدت نگهداری لاگ (روز)', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="log_retention" name="advanced[log_retention]" 
                   value="<?php echo esc_attr($advanced_config['log_retention'] ?? 30); ?>" 
                   min="1" max="365" class="small-text">
            <p class="forooshyar-field-description">
                <?php _e('تعداد روزهایی که لاگ‌ها نگهداری می‌شوند', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="api_hooks"><?php _e('هوک‌های API', 'forooshyar'); ?></label>
        </th>
        <td>
            <textarea id="api_hooks" name="advanced[api_hooks]" 
                      class="large-text code" rows="6" 
                      placeholder='<?php _e('مثال:', 'forooshyar'); ?>&#10;{&#10;  "before_product_fetch": "my_custom_function",&#10;  "after_response_format": "another_function"&#10;}'><?php 
                echo esc_textarea($advanced_config['api_hooks'] ?? ''); 
            ?></textarea>
            <p class="forooshyar-field-description">
                <?php _e('هوک‌های سفارشی برای اجرا در نقاط مختلف API. فرمت JSON.', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="response_filters"><?php _e('فیلترهای پاسخ', 'forooshyar'); ?></label>
        </th>
        <td>
            <textarea id="response_filters" name="advanced[response_filters]" 
                      class="large-text code" rows="6" 
                      placeholder='<?php _e('مثال:', 'forooshyar'); ?>&#10;{&#10;  "remove_empty_fields": true,&#10;  "sanitize_html": true,&#10;  "custom_transformations": []&#10;}'><?php 
                echo esc_textarea($advanced_config['response_filters'] ?? ''); 
            ?></textarea>
            <p class="forooshyar-field-description">
                <?php _e('فیلترهای اعمال شده بر روی پاسخ‌های API. فرمت JSON.', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="circuit_breaker"><?php _e('Circuit Breaker', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="circuit_breaker_enabled" name="advanced[circuit_breaker][enabled]" value="1" 
                   <?php checked($advanced_config['circuit_breaker']['enabled'] ?? false); ?>>
            <label for="circuit_breaker_enabled"><?php _e('فعال‌سازی Circuit Breaker', 'forooshyar'); ?></label>
            
            <div id="circuit_breaker_settings" style="margin-top: 15px; <?php echo ($advanced_config['circuit_breaker']['enabled'] ?? false) ? '' : 'display: none;'; ?>">
                <table class="forooshyar-nested-table">
                    <tr>
                        <td style="width: 200px;">
                            <label for="failure_threshold"><?php _e('آستانه خطا', 'forooshyar'); ?></label>
                        </td>
                        <td>
                            <input type="number" id="failure_threshold" name="advanced[circuit_breaker][failure_threshold]" 
                                   value="<?php echo esc_attr($advanced_config['circuit_breaker']['failure_threshold'] ?? 5); ?>" 
                                   min="1" max="100" class="small-text">
                            <span class="description"><?php _e('تعداد خطاهای متوالی قبل از فعال‌سازی', 'forooshyar'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="recovery_timeout"><?php _e('زمان بازیابی (ثانیه)', 'forooshyar'); ?></label>
                        </td>
                        <td>
                            <input type="number" id="recovery_timeout" name="advanced[circuit_breaker][recovery_timeout]" 
                                   value="<?php echo esc_attr($advanced_config['circuit_breaker']['recovery_timeout'] ?? 60); ?>" 
                                   min="10" max="3600" class="small-text">
                            <span class="description"><?php _e('زمان انتظار قبل از تلاش مجدد', 'forooshyar'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="forooshyar-field-description">
                <?php _e('محافظت در برابر خطاهای متوالی و جلوگیری از اختلال در سیستم', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
</table>

<div class="forooshyar-advanced-actions">
    <h4><?php _e('عملیات پیشرفته', 'forooshyar'); ?></h4>
    
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('تست عملکرد', 'forooshyar'); ?></th>
            <td>
                <button type="button" id="run-performance-test" class="button">
                    <?php _e('اجرای تست عملکرد', 'forooshyar'); ?>
                </button>
                <p class="description">
                    <?php _e('تست سرعت و عملکرد API با داده‌های نمونه', 'forooshyar'); ?>
                </p>
                <div id="performance-results" class="forooshyar-test-results" style="display: none;"></div>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('پاک‌سازی داده‌ها', 'forooshyar'); ?></th>
            <td>
                <button type="button" id="cleanup-logs" class="button">
                    <?php _e('پاک کردن لاگ‌های قدیمی', 'forooshyar'); ?>
                </button>
                <button type="button" id="cleanup-cache" class="button">
                    <?php _e('پاک کردن کش منقضی', 'forooshyar'); ?>
                </button>
                <button type="button" id="optimize-database" class="button">
                    <?php _e('بهینه‌سازی پایگاه داده', 'forooshyar'); ?>
                </button>
                <p class="description">
                    <?php _e('عملیات نگهداری و بهینه‌سازی سیستم', 'forooshyar'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('صادرات/وارد کردن', 'forooshyar'); ?></th>
            <td>
                <button type="button" id="export-all-settings" class="button">
                    <?php _e('صادرات کامل تنظیمات', 'forooshyar'); ?>
                </button>
                <input type="file" id="import-all-settings-file" accept=".json" style="display: none;">
                <button type="button" id="import-all-settings" class="button">
                    <?php _e('وارد کردن کامل تنظیمات', 'forooshyar'); ?>
                </button>
                <p class="description">
                    <?php _e('صادرات و وارد کردن تمام تنظیمات شامل پیکربندی‌های پیشرفته', 'forooshyar'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<style>
.forooshyar-advanced-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.forooshyar-advanced-actions h4 {
    color: #1e73be;
    margin-bottom: 15px;
}

.forooshyar-test-results {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-top: 10px;
    font-family: monospace;
    font-size: 12px;
}

.forooshyar-nested-table {
    width: 100%;
    border-collapse: collapse;
}

.forooshyar-nested-table td {
    padding: 8px 0;
    vertical-align: top;
}

.forooshyar-nested-table td:first-child {
    font-weight: 500;
}

#circuit_breaker_settings {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle circuit breaker settings
    $('#circuit_breaker_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#circuit_breaker_settings').slideDown();
        } else {
            $('#circuit_breaker_settings').slideUp();
        }
    });
    
    // Validate JSON inputs
    $('#custom_field_mapping, #api_hooks, #response_filters').on('blur', function() {
        validateJSON($(this));
    });
    
    // Performance test
    $('#run-performance-test').on('click', function() {
        runPerformanceTest();
    });
    
    // Cleanup operations
    $('#cleanup-logs').on('click', function() {
        performCleanup('logs');
    });
    
    $('#cleanup-cache').on('click', function() {
        performCleanup('cache');
    });
    
    $('#optimize-database').on('click', function() {
        performCleanup('database');
    });
    
    // Export/Import all settings
    $('#export-all-settings').on('click', function() {
        exportAllSettings();
    });
    
    $('#import-all-settings').on('click', function() {
        $('#import-all-settings-file').click();
    });
    
    $('#import-all-settings-file').on('change', function(e) {
        importAllSettings(e.target.files[0]);
    });
    
    function validateJSON($element) {
        var value = $element.val().trim();
        
        if (value && value !== '') {
            try {
                JSON.parse(value);
                $element.removeClass('forooshyar-json-error');
            } catch (e) {
                $element.addClass('forooshyar-json-error');
                alert('<?php _e("فرمت JSON نامعتبر است", "forooshyar"); ?>: ' + e.message);
            }
        }
    }
    
    function runPerformanceTest() {
        var $button = $('#run-performance-test');
        var $results = $('#performance-results');
        
        $button.prop('disabled', true).text('<?php _e("در حال اجرا...", "forooshyar"); ?>');
        $results.show().html('<?php _e("در حال اجرای تست عملکرد...", "forooshyar"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'forooshyar_performance_test',
                nonce: '<?php echo wp_create_nonce("forooshyar_advanced"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var results = response.data;
                    var html = '<strong><?php _e("نتایج تست عملکرد:", "forooshyar"); ?></strong><br>';
                    html += '<?php _e("زمان پاسخ میانگین:", "forooshyar"); ?> ' + results.avg_response_time + ' ms<br>';
                    html += '<?php _e("حداکثر زمان پاسخ:", "forooshyar"); ?> ' + results.max_response_time + ' ms<br>';
                    html += '<?php _e("مصرف حافظه:", "forooshyar"); ?> ' + results.memory_usage + ' MB<br>';
                    html += '<?php _e("تعداد کوئری‌ها:", "forooshyar"); ?> ' + results.query_count;
                    $results.html(html);
                } else {
                    $results.html('<?php _e("خطا در اجرای تست:", "forooshyar"); ?> ' + response.data.message);
                }
            },
            error: function() {
                $results.html('<?php _e("خطا در ارتباط با سرور", "forooshyar"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e("اجرای تست عملکرد", "forooshyar"); ?>');
            }
        });
    }
    
    function performCleanup(type) {
        var confirmMessage = '';
        switch (type) {
            case 'logs':
                confirmMessage = '<?php _e("آیا مطمئن هستید که می‌خواهید لاگ‌های قدیمی را پاک کنید؟", "forooshyar"); ?>';
                break;
            case 'cache':
                confirmMessage = '<?php _e("آیا مطمئن هستید که می‌خواهید کش منقضی را پاک کنید؟", "forooshyar"); ?>';
                break;
            case 'database':
                confirmMessage = '<?php _e("آیا مطمئن هستید که می‌خواهید پایگاه داده را بهینه‌سازی کنید؟", "forooshyar"); ?>';
                break;
        }
        
        if (confirm(confirmMessage)) {
            var $button = $('#cleanup-' + type + ', #optimize-database');
            $button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'forooshyar_cleanup',
                    cleanup_type: type,
                    nonce: '<?php echo wp_create_nonce("forooshyar_advanced"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('<?php _e("خطا:", "forooshyar"); ?> ' + response.data.message);
                    }
                },
                error: function() {
                    alert('<?php _e("خطا در ارتباط با سرور", "forooshyar"); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        }
    }
    
    function exportAllSettings() {
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'forooshyar_export_all_settings'
            },
            success: function(response) {
                if (response.success) {
                    var dataStr = JSON.stringify(response.data, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = 'forooshyar-complete-settings-' + new Date().toISOString().split('T')[0] + '.json';
                    link.click();
                } else {
                    alert('<?php _e("خطا در صادرات تنظیمات:", "forooshyar"); ?> ' + response.data.message);
                }
            }
        });
    }
    
    function importAllSettings(file) {
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'forooshyar_import_all_settings',
                            settings: JSON.stringify(settings),
                            nonce: '<?php echo wp_create_nonce("forooshyar_advanced"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('<?php _e("تنظیمات با موفقیت وارد شد", "forooshyar"); ?>');
                                location.reload();
                            } else {
                                alert('<?php _e("خطا در وارد کردن تنظیمات:", "forooshyar"); ?> ' + response.data.message);
                            }
                        }
                    });
                } catch (error) {
                    alert('<?php _e("فایل تنظیمات نامعتبر است", "forooshyar"); ?>');
                }
            };
            reader.readAsText(file);
        }
    }
});
</script>

<style>
.forooshyar-json-error {
    border-color: #dc3232 !important;
    box-shadow: 0 0 2px rgba(220, 50, 50, 0.8);
}
</style>