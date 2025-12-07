<?php
/**
 * Product Fields Settings Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$fields_config = $config['fields'] ?? [];

// Default fields that should be available
$available_fields = [
    'title' => __('عنوان', 'forooshyar'),
    'subtitle' => __('زیرعنوان', 'forooshyar'),
    'parent_id' => __('شناسه والد', 'forooshyar'),
    'page_unique' => __('شناسه یکتای صفحه', 'forooshyar'),
    'current_price' => __('قیمت فعلی', 'forooshyar'),
    'old_price' => __('قیمت قبلی', 'forooshyar'),
    'availability' => __('موجودی', 'forooshyar'),
    'category_name' => __('نام دسته‌بندی', 'forooshyar'),
    'image_links' => __('لینک‌های تصاویر', 'forooshyar'),
    'image_link' => __('لینک تصویر اصلی', 'forooshyar'),
    'page_url' => __('آدرس صفحه', 'forooshyar'),
    'short_desc' => __('توضیح کوتاه', 'forooshyar'),
    'spec' => __('مشخصات', 'forooshyar'),
    'date' => __('تاریخ', 'forooshyar'),
    'registry' => __('رجیستری', 'forooshyar'),
    'guarantee' => __('گارانتی', 'forooshyar')
];
?>

<div class="forooshyar-fields-section">
    <h4><?php _e('انتخاب فیلدهای محصول', 'forooshyar'); ?></h4>
    <p class="description">
        <?php _e('فیلدهایی که می‌خواهید در پاسخ‌های API گنجانده شوند را انتخاب کنید. همه فیلدها به طور پیش‌فرض فعال هستند.', 'forooshyar'); ?>
    </p>
    
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('فیلدهای اصلی', 'forooshyar'); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('انتخاب فیلدهای محصول', 'forooshyar'); ?></span>
                    </legend>
                    
                    <div class="forooshyar-fields-grid">
                        <?php foreach ($available_fields as $field_key => $field_label): ?>
                            <label class="forooshyar-field-checkbox">
                                <input type="checkbox" 
                                       name="fields[<?php echo esc_attr($field_key); ?>]" 
                                       value="1" 
                                       <?php checked($fields_config[$field_key] ?? true); ?>>
                                <span class="forooshyar-field-label"><?php echo esc_html($field_label); ?></span>
                                <code class="forooshyar-field-key"><?php echo esc_html($field_key); ?></code>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="select_all_fields"><?php _e('عملیات سریع', 'forooshyar'); ?></label>
            </th>
            <td>
                <button type="button" id="select_all_fields" class="button">
                    <?php _e('انتخاب همه', 'forooshyar'); ?>
                </button>
                <button type="button" id="deselect_all_fields" class="button">
                    <?php _e('لغو انتخاب همه', 'forooshyar'); ?>
                </button>
                <button type="button" id="reset_default_fields" class="button">
                    <?php _e('بازگردانی به پیش‌فرض', 'forooshyar'); ?>
                </button>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="custom_fields"><?php _e('فیلدهای سفارشی', 'forooshyar'); ?></label>
            </th>
            <td>
                <textarea id="custom_fields" name="fields[custom_fields]" 
                          class="large-text" rows="4" 
                          placeholder="<?php _e('فیلدهای سفارشی را به صورت JSON وارد کنید', 'forooshyar'); ?>"><?php 
                    echo esc_textarea($fields_config['custom_fields'] ?? ''); 
                ?></textarea>
                <p class="forooshyar-field-description">
                    <?php _e('فیلدهای سفارشی اضافی که می‌خواهید از متافیلدهای محصول استخراج شوند. فرمت: {"field_name": "meta_key"}', 'forooshyar'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="field_mapping"><?php _e('نگاشت فیلدها', 'forooshyar'); ?></label>
            </th>
            <td>
                <textarea id="field_mapping" name="fields[field_mapping]" 
                          class="large-text" rows="4" 
                          placeholder="<?php _e('نگاشت نام فیلدها را به صورت JSON وارد کنید', 'forooshyar'); ?>"><?php 
                    echo esc_textarea($fields_config['field_mapping'] ?? ''); 
                ?></textarea>
                <p class="forooshyar-field-description">
                    <?php _e('برای تغییر نام فیلدها در پاسخ API استفاده کنید. فرمت: {"old_name": "new_name"}', 'forooshyar'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<style>
.forooshyar-fields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.forooshyar-field-checkbox {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
    cursor: pointer;
    transition: background-color 0.2s;
}

.forooshyar-field-checkbox:hover {
    background: #f0f0f0;
}

.forooshyar-field-checkbox input[type="checkbox"] {
    margin: 0 8px 0 0;
}

.forooshyar-field-label {
    flex: 1;
    font-weight: 500;
}

.forooshyar-field-key {
    font-size: 11px;
    color: #666;
    background: #e0e0e0;
    padding: 2px 6px;
    border-radius: 3px;
    margin-right: 8px;
}

.forooshyar-fields-section h4 {
    color: #1e73be;
    margin-bottom: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all fields
    $('#select_all_fields').on('click', function() {
        $('.forooshyar-field-checkbox input[type="checkbox"]').prop('checked', true);
    });
    
    // Deselect all fields
    $('#deselect_all_fields').on('click', function() {
        $('.forooshyar-field-checkbox input[type="checkbox"]').prop('checked', false);
    });
    
    // Reset to default (all selected)
    $('#reset_default_fields').on('click', function() {
        $('.forooshyar-field-checkbox input[type="checkbox"]').prop('checked', true);
    });
    
    // Validate JSON inputs
    $('#custom_fields, #field_mapping').on('blur', function() {
        var $this = $(this);
        var value = $this.val().trim();
        
        if (value && value !== '') {
            try {
                JSON.parse(value);
                $this.removeClass('forooshyar-json-error');
            } catch (e) {
                $this.addClass('forooshyar-json-error');
                alert('<?php _e("فرمت JSON نامعتبر است", "forooshyar"); ?>: ' + e.message);
            }
        }
    });
});
</script>

<style>
.forooshyar-json-error {
    border-color: #dc3232 !important;
    box-shadow: 0 0 2px rgba(220, 50, 50, 0.8);
}
</style>