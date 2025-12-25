<?php
/**
 * Admin Settings Page Template
 * Persian interface for Forooshyar plugin configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap forooshyar-admin" dir="rtl">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <div class="forooshyar-admin-header">
        <div class="forooshyar-logo">
            <h2><?php _e('پنل مدیریت فروشیار', 'forooshyar'); ?></h2>
            <p><?php _e('تنظیمات و پیکربندی API محصولات ووکامرس', 'forooshyar'); ?></p>
            <div class="forooshyar-header-meta">
                <span class="forooshyar-current-date" data-tooltip-fa="<?php _e('تاریخ شمسی امروز', 'forooshyar'); ?>"></span>
                <span class="forooshyar-current-time" data-tooltip-fa="<?php _e('ساعت فعلی', 'forooshyar'); ?>"></span>
                <span class="forooshyar-version" data-tooltip-fa="<?php _e('نسخه افزونه', 'forooshyar'); ?>">
                    <?php _e('نسخه', 'forooshyar'); ?> ۱.۰.۰
                </span>
            </div>
        </div>
    </div>

    <nav class="nav-tab-wrapper forooshyar-nav-tabs">
        <a href="?page=forooshyar&tab=general" 
           class="nav-tab <?php echo ($current_tab === 'general') ? 'nav-tab-active' : ''; ?>">
            <?php _e('عمومی', 'forooshyar'); ?>
        </a>
        <a href="?page=forooshyar&tab=fields" 
           class="nav-tab <?php echo ($current_tab === 'fields') ? 'nav-tab-active' : ''; ?>">
            <?php _e('فیلدهای محصول', 'forooshyar'); ?>
        </a>
        <!-- <a href="?page=forooshyar&tab=images" 
           class="nav-tab <?php echo ($current_tab === 'images') ? 'nav-tab-active' : ''; ?>">
            <?php _e('تصاویر', 'forooshyar'); ?>
        </a> -->
        <a href="?page=forooshyar&tab=cache" 
           class="nav-tab <?php echo ($current_tab === 'cache') ? 'nav-tab-active' : ''; ?>">
            <?php _e('کش', 'forooshyar'); ?>
        </a>
        <a href="?page=forooshyar&tab=api" 
           class="nav-tab <?php echo ($current_tab === 'api') ? 'nav-tab-active' : ''; ?>">
            <?php _e('محدودیت‌های API', 'forooshyar'); ?>
        </a>
        <?php if (get_option('aiagent_module_enabled', false) || current_user_can('manage_options')): ?>
        <a href="?page=forooshyar&tab=aiagent" 
           class="nav-tab <?php echo ($current_tab === 'aiagent') ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-chart-line" style="font-size: 16px; vertical-align: middle; margin-left: 5px;"></span>
            <?php _e('دستیار هوشمند', 'forooshyar'); ?>
        </a>
        <?php endif; ?>
        <!-- <a href="?page=forooshyar&tab=advanced" 
           class="nav-tab <?php echo ($current_tab === 'advanced') ? 'nav-tab-active' : ''; ?>">
            <?php _e('پیشرفته', 'forooshyar'); ?>
        </a> -->
    </nav>

    <form id="forooshyar-settings-form" method="post">
        <?php wp_nonce_field('forooshyar_settings', 'forooshyar_nonce'); ?>
        
        <div class="forooshyar-tab-content">
            <?php
            switch ($current_tab) {
                case 'general':
                    include __DIR__ . '/partials/general-tab.view.php';
                    break;
                case 'fields':
                    include __DIR__ . '/partials/fields-tab.view.php';
                    break;
                case 'images':
                    include __DIR__ . '/partials/images-tab.view.php';
                    break;
                case 'cache':
                    include __DIR__ . '/partials/cache-tab.view.php';
                    break;
                case 'api':
                    include __DIR__ . '/partials/api-tab.view.php';
                    break;
                case 'advanced':
                    include __DIR__ . '/partials/advanced-tab.view.php';
                    break;
                case 'aiagent':
                    include __DIR__ . '/partials/aiagent-tab.view.php';
                    break;
                default:
                    include __DIR__ . '/partials/general-tab.view.php';
            }
            ?>
        </div>

        <?php if ($current_tab !== 'aiagent'): ?>
        <div class="forooshyar-form-actions">
            <button type="submit" class="button button-primary button-large" id="forooshyar-save-btn">
                <?php _e('ذخیره تنظیمات', 'forooshyar'); ?>
            </button>
            <span id="forooshyar-save-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
            <button type="button" id="forooshyar-reset-settings" class="button button-secondary">
                <?php _e('بازگردانی به پیش‌فرض', 'forooshyar'); ?>
            </button>
            <button type="button" id="forooshyar-export-settings" class="button">
                <?php _e('صادرات تنظیمات', 'forooshyar'); ?>
            </button>
            <input type="file" id="forooshyar-import-file" accept=".json" style="display: none;">
            <button type="button" id="forooshyar-import-settings" class="button">
                <?php _e('وارد کردن تنظیمات', 'forooshyar'); ?>
            </button>
        </div>
        <?php endif; ?>
    </form>

    <div id="forooshyar-messages" class="forooshyar-messages"></div>
</div>

<style>
.forooshyar-admin {
    font-family: 'Vazir', 'Tahoma', sans-serif;
}

.forooshyar-admin-header {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.forooshyar-logo h2 {
    color: white;
    margin: 0 0 10px 0;
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
}

.forooshyar-nav-tabs {
    margin-bottom: 0;
}

.forooshyar-tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
    min-height: 400px;
}

.forooshyar-form-actions {
    margin-top: 20px;
    padding: 15px 20px;
    background: #f9f9f9;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.forooshyar-form-actions .button {
    margin-left: 10px;
}

.forooshyar-messages {
    margin-top: 20px;
}

.forooshyar-messages .notice {
    margin: 5px 0;
}

.form-table th {
    text-align: right;
    padding-right: 0;
    padding-left: 20px;
}

.form-table td {
    padding-left: 0;
}

.forooshyar-field-description {
    font-style: italic;
    color: #666;
    margin-top: 5px;
}

.forooshyar-template-variables {
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
}

.forooshyar-template-variables h4 {
    margin: 0 0 10px 0;
    color: #1d2327;
}

.forooshyar-template-variables code {
    background: #fff;
    padding: 2px 4px;
    border-radius: 2px;
    margin: 2px;
    display: inline-block;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle form submission
    $('#forooshyar-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $saveBtn = $('#forooshyar-save-btn');
        var $spinner = $('#forooshyar-save-spinner');
        
        // Show loading state
        $saveBtn.prop('disabled', true);
        $spinner.addClass('is-active');
        
        var formData = new FormData(this);
        formData.append('action', 'forooshyar_save_settings');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('<?php _e("خطا در ارتباط با سرور", "forooshyar"); ?>', 'error');
            },
            complete: function() {
                // Hide loading state
                $saveBtn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Handle reset settings
    $('#forooshyar-reset-settings').on('click', function() {
        if (confirm('<?php _e("آیا مطمئن هستید که می‌خواهید تمام تنظیمات را به حالت پیش‌فرض بازگردانید؟", "forooshyar"); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'forooshyar_reset_settings',
                    nonce: $('#forooshyar_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        location.reload();
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                }
            });
        }
    });
    
    // Handle export settings
    $('#forooshyar-export-settings').on('click', function() {
        var settings = getFormSettings();
        var dataStr = JSON.stringify(settings, null, 2);
        var dataBlob = new Blob([dataStr], {type: 'application/json'});
        
        var link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = 'forooshyar-settings-' + new Date().toISOString().split('T')[0] + '.json';
        link.click();
    });
    
    // Handle import settings
    $('#forooshyar-import-settings').on('click', function() {
        $('#forooshyar-import-file').click();
    });
    
    $('#forooshyar-import-file').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    setFormSettings(settings);
                    showMessage('<?php _e("تنظیمات با موفقیت وارد شد", "forooshyar"); ?>', 'success');
                } catch (error) {
                    showMessage('<?php _e("فایل تنظیمات نامعتبر است", "forooshyar"); ?>', 'error');
                }
            };
            reader.readAsText(file);
        }
    });
    
    function showMessage(message, type) {
        var messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        var messageHtml = '<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';
        $('#forooshyar-messages').html(messageHtml);
        
        setTimeout(function() {
            $('#forooshyar-messages .notice').fadeOut();
        }, 5000);
    }
    
    function getFormSettings() {
        var settings = {};
        $('#forooshyar-settings-form').find('input, select, textarea').each(function() {
            var $this = $(this);
            var name = $this.attr('name');
            if (name && name !== 'forooshyar_nonce' && name !== '_wp_http_referer') {
                if ($this.attr('type') === 'checkbox') {
                    settings[name] = $this.is(':checked');
                } else {
                    settings[name] = $this.val();
                }
            }
        });
        return settings;
    }
    
    function setFormSettings(settings) {
        $.each(settings, function(name, value) {
            var $field = $('#forooshyar-settings-form').find('[name="' + name + '"]');
            if ($field.length) {
                if ($field.attr('type') === 'checkbox') {
                    $field.prop('checked', value);
                } else {
                    $field.val(value);
                }
            }
        });
    }
});
</script>