<?php
/**
 * General Settings Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$general_config = $config['general'] ?? [];
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="show_variations"><?php _e('نمایش تنوع‌ها', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="show_variations" name="general[show_variations]" value="1" 
                   <?php checked($general_config['show_variations'] ?? true); ?>>
            <p class="forooshyar-field-description">
                <?php _e('آیا تنوع‌های محصولات در پاسخ API گنجانده شوند؟', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="title_template"><?php _e('قالب عنوان', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="text" id="title_template" name="general[title_template]" 
                   value="<?php echo esc_attr($general_config['title_template'] ?? '{{product_name}}{{variation_suffix}}'); ?>" 
                   class="large-text forooshyar-template-input" 
                   data-tooltip-fa="<?php _e('از متغیرهای موجود برای ساخت قالب عنوان استفاده کنید', 'forooshyar'); ?>">
            <p class="forooshyar-field-description">
                <?php _e('قالب برای تولید عنوان محصولات. از متغیرهای زیر استفاده کنید:', 'forooshyar'); ?>
            </p>
            
            <div class="forooshyar-template-variables">
                <h4><?php _e('متغیرهای قابل استفاده:', 'forooshyar'); ?></h4>
                <div class="forooshyar-variables-grid">
                    <?php 
                    $default_variables = [
                        'product_name' => __('نام محصول', 'forooshyar'),
                        'variation_name' => __('نام تنوع', 'forooshyar'),
                        'variation_suffix' => __('پسوند تنوع', 'forooshyar'),
                        'category' => __('دسته‌بندی', 'forooshyar'),
                        'sku' => __('کد محصول', 'forooshyar'),
                        'brand' => __('برند', 'forooshyar'),
                        'custom_suffix' => __('پسوند سفارشی', 'forooshyar')
                    ];
                    
                    $vars_to_show = !empty($variables) ? $variables : $default_variables;
                    ?>
                    <?php foreach ($vars_to_show as $var => $description): ?>
                        <div class="forooshyar-variable-item" onclick="insertVariable('{{<?php echo esc_js($var); ?>}}')">
                            <code>{{<?php echo esc_html($var); ?>}}</code>
                            <span><?php echo esc_html($description); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="forooshyar-template-preview">
                    <h5><?php _e('پیش‌نمایش:', 'forooshyar'); ?></h5>
                    <div id="template-preview" class="forooshyar-preview-text">
                        <?php _e('نمونه محصول - تنوع قرمز', 'forooshyar'); ?>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="custom_suffix"><?php _e('پسوند سفارشی', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="text" id="custom_suffix" name="general[custom_suffix]" 
                   value="<?php echo esc_attr($general_config['custom_suffix'] ?? ''); ?>" 
                   class="regular-text">
            <p class="forooshyar-field-description">
                <?php _e('متن سفارشی که به انتهای عناوین اضافه می‌شود', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="language"><?php _e('زبان پیش‌فرض', 'forooshyar'); ?></label>
        </th>
        <td>
            <select id="language" name="general[language]" class="regular-text">
                <option value="fa_IR" <?php selected($general_config['language'] ?? 'fa_IR', 'fa_IR'); ?>>
                    <?php _e('فارسی', 'forooshyar'); ?>
                </option>
                <option value="en_US" <?php selected($general_config['language'] ?? 'fa_IR', 'en_US'); ?>>
                    <?php _e('انگلیسی', 'forooshyar'); ?>
                </option>
            </select>
            <p class="forooshyar-field-description">
                <?php _e('زبان پیش‌فرض برای رابط کاربری و پیام‌های خطا', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="api_version"><?php _e('نسخه API', 'forooshyar'); ?></label>
        </th>
        <td>
            <select id="api_version" name="general[api_version]" class="regular-text">
                <option value="v1" <?php selected($general_config['api_version'] ?? 'v1', 'v1'); ?>>
                    <?php _e('نسخه 1', 'forooshyar'); ?>
                </option>
            </select>
            <p class="forooshyar-field-description">
                <?php _e('نسخه API که در URL نقاط پایانی استفاده می‌شود', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="debug_mode"><?php _e('حالت اشکال‌زدایی', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="debug_mode" name="general[debug_mode]" value="1" 
                   <?php checked($general_config['debug_mode'] ?? false); ?>>
            <p class="forooshyar-field-description">
                <?php _e('فعال‌سازی لاگ‌گیری تفصیلی برای اشکال‌زدایی', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
</table>

<style>
.forooshyar-variables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px;
    margin: 10px 0;
}

.forooshyar-variable-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.forooshyar-variable-item:hover {
    background: #e9ecef;
    border-color: #1e73be;
    transform: translateY(-1px);
}

.forooshyar-variable-item code {
    background: #1e73be;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 8px;
    font-size: 11px;
    min-width: 80px;
    text-align: center;
}

.forooshyar-variable-item span {
    font-size: 12px;
    color: #495057;
}

.forooshyar-template-preview {
    margin-top: 15px;
    padding: 10px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.forooshyar-template-preview h5 {
    margin: 0 0 8px 0;
    color: #1e73be;
    font-size: 13px;
}

.forooshyar-preview-text {
    font-weight: 500;
    color: #28a745;
    font-size: 14px;
    padding: 5px;
    background: #f8fff9;
    border-radius: 3px;
    border-right: 3px solid #28a745;
}

.forooshyar-template-input {
    font-family: 'Courier New', monospace !important;
    direction: ltr;
    text-align: left;
}
</style>

<script>
function insertVariable(variable) {
    var input = document.getElementById('title_template');
    var cursorPos = input.selectionStart;
    var textBefore = input.value.substring(0, cursorPos);
    var textAfter = input.value.substring(input.selectionEnd);
    
    input.value = textBefore + variable + textAfter;
    input.focus();
    input.setSelectionRange(cursorPos + variable.length, cursorPos + variable.length);
    
    updateTemplatePreview();
    
    var variableItems = document.querySelectorAll('.forooshyar-variable-item');
    variableItems.forEach(function(item) {
        if (item.querySelector('code').textContent === variable) {
            item.style.background = '#d4edda';
            item.style.borderColor = '#28a745';
            setTimeout(function() {
                item.style.background = '';
                item.style.borderColor = '';
            }, 500);
        }
    });
}

function updateTemplatePreview() {
    var template = document.getElementById('title_template').value;
    var preview = document.getElementById('template-preview');
    var customSuffixInput = document.getElementById('custom_suffix');
    var customSuffix = customSuffixInput ? customSuffixInput.value : '';
    
    var sampleData = {
        'product_name': 'نمونه محصول',
        'variation_name': 'قرمز',
        'variation_suffix': ' - قرمز، سایز L',
        'category': 'لباس',
        'sku': 'PRD-001',
        'brand': 'برند نمونه',
        'custom_suffix': customSuffix || ''
    };
    
    var previewText = template;
    
    Object.keys(sampleData).forEach(function(key) {
        var pattern = '{{' + key + '}}';
        while (previewText.indexOf(pattern) !== -1) {
            previewText = previewText.replace(pattern, sampleData[key]);
        }
    });
    
    if (!previewText.trim()) {
        previewText = 'نمونه محصول';
    }
    
    preview.textContent = previewText;
}

document.addEventListener('DOMContentLoaded', function() {
    updateTemplatePreview();
    
    var titleInput = document.getElementById('title_template');
    if (titleInput) {
        titleInput.addEventListener('input', updateTemplatePreview);
    }
    
    var customSuffixInput = document.getElementById('custom_suffix');
    if (customSuffixInput) {
        customSuffixInput.addEventListener('input', updateTemplatePreview);
    }
});
</script>
