<?php
/**
 * API Limits Settings Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$api_config = $config['api'] ?? [];
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="max_per_page"><?php _e('حداکثر محصول در هر صفحه', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="max_per_page" name="api[max_per_page]" 
                   value="<?php echo esc_attr($api_config['max_per_page'] ?? 100); ?>" 
                   min="1" max="1000" class="small-text">
            <p class="forooshyar-field-description">
                <?php _e('حداکثر تعداد محصولاتی که در یک درخواست API برگردانده می‌شود', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="rate_limit_enabled"><?php _e('محدودیت نرخ درخواست', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="rate_limit_enabled" name="api[rate_limit][enabled]" value="1" 
                   <?php checked($api_config['rate_limit']['enabled'] ?? false); ?>>
            <p class="forooshyar-field-description">
                <?php _e('فعال‌سازی محدودیت تعداد درخواست‌ها برای جلوگیری از سوء استفاده', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr id="rate_limit_settings" style="<?php echo ($api_config['rate_limit']['enabled'] ?? false) ? '' : 'display: none;'; ?>">
        <th scope="row"><?php _e('تنظیمات محدودیت نرخ', 'forooshyar'); ?></th>
        <td>
            <table class="forooshyar-nested-table">
                <tr>
                    <td style="width: 200px;">
                        <label for="requests_per_minute"><?php _e('درخواست در دقیقه', 'forooshyar'); ?></label>
                    </td>
                    <td>
                        <input type="number" id="requests_per_minute" name="api[rate_limit][per_minute]" 
                               value="<?php echo esc_attr($api_config['rate_limit']['per_minute'] ?? 60); ?>" 
                               min="1" max="1000" class="small-text">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="requests_per_hour"><?php _e('درخواست در ساعت', 'forooshyar'); ?></label>
                    </td>
                    <td>
                        <input type="number" id="requests_per_hour" name="api[rate_limit][per_hour]" 
                               value="<?php echo esc_attr($api_config['rate_limit']['per_hour'] ?? 1000); ?>" 
                               min="1" max="10000" class="small-text">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="requests_per_day"><?php _e('درخواست در روز', 'forooshyar'); ?></label>
                    </td>
                    <td>
                        <input type="number" id="requests_per_day" name="api[rate_limit][per_day]" 
                               value="<?php echo esc_attr($api_config['rate_limit']['per_day'] ?? 10000); ?>" 
                               min="1" max="100000" class="regular-text">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="request_timeout"><?php _e('زمان انقضای درخواست (ثانیه)', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="request_timeout" name="api[timeout]" 
                   value="<?php echo esc_attr($api_config['timeout'] ?? 30); ?>" 
                   min="5" max="300" class="small-text">
            <p class="forooshyar-field-description">
                <?php _e('حداکثر زمان پردازش یک درخواست API', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="ip_whitelist"><?php _e('لیست سفید IP', 'forooshyar'); ?></label>
        </th>
        <td>
            <textarea id="ip_whitelist" name="api[ip_whitelist]" 
                      class="large-text" rows="4" 
                      placeholder="192.168.1.1&#10;10.0.0.0/8&#10;example.com"><?php 
                echo esc_textarea($api_config['ip_whitelist'] ?? ''); 
            ?></textarea>
            <p class="forooshyar-field-description">
                <?php _e('آدرس‌های IP یا دامنه‌هایی که مجاز به دسترسی هستند (هر خط یک آدرس). خالی = همه مجاز', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="ip_blacklist"><?php _e('لیست سیاه IP', 'forooshyar'); ?></label>
        </th>
        <td>
            <textarea id="ip_blacklist" name="api[ip_blacklist]" 
                      class="large-text" rows="4" 
                      placeholder="192.168.1.100&#10;spam-bot.com"><?php 
                echo esc_textarea($api_config['ip_blacklist'] ?? ''); 
            ?></textarea>
            <p class="forooshyar-field-description">
                <?php _e('آدرس‌های IP یا دامنه‌هایی که از دسترسی محروم هستند (هر خط یک آدرس)', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="require_user_agent"><?php _e('الزام User Agent', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="require_user_agent" name="api[require_user_agent]" value="1" 
                   <?php checked($api_config['require_user_agent'] ?? false); ?>>
            <p class="forooshyar-field-description">
                <?php _e('الزام ارسال User Agent در درخواست‌ها', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="allowed_user_agents"><?php _e('User Agent های مجاز', 'forooshyar'); ?></label>
        </th>
        <td>
            <textarea id="allowed_user_agents" name="api[allowed_user_agents]" 
                      class="large-text" rows="3" 
                      placeholder="MyApp/1.0&#10;curl/*&#10;*bot*"><?php 
                echo esc_textarea($api_config['allowed_user_agents'] ?? ''); 
            ?></textarea>
            <p class="forooshyar-field-description">
                <?php _e('الگوهای User Agent مجاز (هر خط یک الگو). خالی = همه مجاز. از * برای wildcard استفاده کنید', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cors_enabled"><?php _e('فعال‌سازی CORS', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="cors_enabled" name="api[cors][enabled]" value="1" 
                   <?php checked($api_config['cors']['enabled'] ?? true); ?>>
            <p class="forooshyar-field-description">
                <?php _e('فعال‌سازی Cross-Origin Resource Sharing برای دسترسی از مرورگرها', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr id="cors_settings" style="<?php echo ($api_config['cors']['enabled'] ?? true) ? '' : 'display: none;'; ?>">
        <th scope="row"><?php _e('تنظیمات CORS', 'forooshyar'); ?></th>
        <td>
            <table class="forooshyar-nested-table">
                <tr>
                    <td style="width: 200px;">
                        <label for="cors_origins"><?php _e('Origin های مجاز', 'forooshyar'); ?></label>
                    </td>
                    <td>
                        <input type="text" id="cors_origins" name="api[cors][origins]" 
                               value="<?php echo esc_attr($api_config['cors']['origins'] ?? '*'); ?>" 
                               class="large-text" placeholder="* یا https://example.com,https://app.com">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="cors_methods"><?php _e('متدهای مجاز', 'forooshyar'); ?></label>
                    </td>
                    <td>
                        <input type="text" id="cors_methods" name="api[cors][methods]" 
                               value="<?php echo esc_attr($api_config['cors']['methods'] ?? 'GET,POST,OPTIONS'); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="cors_headers"><?php _e('Header های مجاز', 'forooshyar'); ?></label>
                    </td>
                    <td>
                        <input type="text" id="cors_headers" name="api[cors][headers]" 
                               value="<?php echo esc_attr($api_config['cors']['headers'] ?? 'Content-Type,Authorization'); ?>" 
                               class="large-text">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="response_compression"><?php _e('فشرده‌سازی پاسخ', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="response_compression" name="api[compression]" value="1" 
                   <?php checked($api_config['compression'] ?? true); ?>>
            <p class="forooshyar-field-description">
                <?php _e('فشرده‌سازی پاسخ‌های API برای کاهش مصرف پهنای باند', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
</table>

<style>
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

#rate_limit_settings,
#cors_settings {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle rate limit settings
    $('#rate_limit_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rate_limit_settings').slideDown();
        } else {
            $('#rate_limit_settings').slideUp();
        }
    });
    
    // Toggle CORS settings
    $('#cors_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#cors_settings').slideDown();
        } else {
            $('#cors_settings').slideUp();
        }
    });
    
    // Validate IP addresses
    $('#ip_whitelist, #ip_blacklist').on('blur', function() {
        var $this = $(this);
        var lines = $this.val().split('\n');
        var hasError = false;
        
        lines.forEach(function(line) {
            line = line.trim();
            if (line && !isValidIPOrDomain(line)) {
                hasError = true;
            }
        });
        
        if (hasError) {
            $this.addClass('forooshyar-validation-error');
            alert('<?php _e("برخی از آدرس‌های IP یا دامنه‌ها نامعتبر هستند", "forooshyar"); ?>');
        } else {
            $this.removeClass('forooshyar-validation-error');
        }
    });
    
    function isValidIPOrDomain(value) {
        // Simple validation for IP addresses, CIDR notation, and domains
        var ipRegex = /^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/;
        var domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/;
        
        return ipRegex.test(value) || domainRegex.test(value);
    }
});
</script>

<style>
.forooshyar-validation-error {
    border-color: #dc3232 !important;
    box-shadow: 0 0 2px rgba(220, 50, 50, 0.8);
}
</style>