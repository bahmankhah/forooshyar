<?php
/**
 * Images Settings Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$images_config = $config['images'] ?? [];
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="image_sizes"><?php _e('اندازه‌های تصاویر', 'forooshyar'); ?></label>
        </th>
        <td>
            <fieldset>
                <legend class="screen-reader-text">
                    <span><?php _e('انتخاب اندازه‌های تصاویر', 'forooshyar'); ?></span>
                </legend>
                
                <?php
                $available_sizes = ['thumbnail', 'medium', 'large', 'full'];
                $selected_sizes = $images_config['sizes'] ?? ['thumbnail', 'medium', 'large', 'full'];
                ?>
                
                <?php foreach ($available_sizes as $size): ?>
                    <label>
                        <input type="checkbox" name="images[sizes][]" value="<?php echo esc_attr($size); ?>" 
                               <?php checked(in_array($size, $selected_sizes)); ?>>
                        <?php 
                        switch ($size) {
                            case 'thumbnail':
                                _e('تصویر کوچک', 'forooshyar');
                                break;
                            case 'medium':
                                _e('متوسط', 'forooshyar');
                                break;
                            case 'large':
                                _e('بزرگ', 'forooshyar');
                                break;
                            case 'full':
                                _e('اندازه اصلی', 'forooshyar');
                                break;
                        }
                        ?>
                    </label><br>
                <?php endforeach; ?>
            </fieldset>
            <p class="forooshyar-field-description">
                <?php _e('اندازه‌های تصاویری که در پاسخ API گنجانده می‌شوند', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="max_images"><?php _e('حداکثر تعداد تصاویر', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="max_images" name="images[max_images]" 
                   value="<?php echo esc_attr($images_config['max_images'] ?? 10); ?>" 
                   min="1" max="50" class="small-text">
            <p class="forooshyar-field-description">
                <?php _e('حداکثر تعداد تصاویری که برای هر محصول در پاسخ API گنجانده می‌شود', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="image_quality"><?php _e('کیفیت تصاویر', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="range" id="image_quality" name="images[quality]" 
                   value="<?php echo esc_attr($images_config['quality'] ?? 80); ?>" 
                   min="10" max="100" step="5" class="forooshyar-range-input">
            <span id="quality_value" class="forooshyar-range-value"><?php echo esc_html($images_config['quality'] ?? 80); ?>%</span>
            <p class="forooshyar-field-description">
                <?php _e('کیفیت فشرده‌سازی تصاویر (بالاتر = کیفیت بهتر، اندازه بیشتر)', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="image_format"><?php _e('فرمت تصاویر', 'forooshyar'); ?></label>
        </th>
        <td>
            <select id="image_format" name="images[format]" class="regular-text">
                <option value="original" <?php selected($images_config['format'] ?? 'original', 'original'); ?>>
                    <?php _e('فرمت اصلی', 'forooshyar'); ?>
                </option>
                <option value="webp" <?php selected($images_config['format'] ?? 'original', 'webp'); ?>>
                    <?php _e('WebP (بهینه)', 'forooshyar'); ?>
                </option>
                <option value="jpeg" <?php selected($images_config['format'] ?? 'original', 'jpeg'); ?>>
                    <?php _e('JPEG', 'forooshyar'); ?>
                </option>
            </select>
            <p class="forooshyar-field-description">
                <?php _e('فرمت تصاویر در پاسخ API. WebP برای بهینه‌سازی اندازه توصیه می‌شود.', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="lazy_loading"><?php _e('بارگذاری تنبل', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="lazy_loading" name="images[lazy_loading]" value="1" 
                   <?php checked($images_config['lazy_loading'] ?? false); ?>>
            <p class="forooshyar-field-description">
                <?php _e('بارگذاری تصاویر فقط هنگام نیاز برای بهبود عملکرد', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="placeholder_image"><?php _e('تصویر جایگزین', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="url" id="placeholder_image" name="images[placeholder]" 
                   value="<?php echo esc_attr($images_config['placeholder'] ?? ''); ?>" 
                   class="large-text">
            <button type="button" id="select_placeholder" class="button">
                <?php _e('انتخاب تصویر', 'forooshyar'); ?>
            </button>
            <p class="forooshyar-field-description">
                <?php _e('تصویری که در صورت عدم وجود تصویر محصول نمایش داده می‌شود', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="image_cdn"><?php _e('CDN تصاویر', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="url" id="image_cdn" name="images[cdn_url]" 
                   value="<?php echo esc_attr($images_config['cdn_url'] ?? ''); ?>" 
                   class="large-text" placeholder="https://cdn.example.com">
            <p class="forooshyar-field-description">
                <?php _e('آدرس CDN برای سرو تصاویر (اختیاری)', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="image_watermark"><?php _e('واترمارک', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="image_watermark" name="images[watermark][enabled]" value="1" 
                   <?php checked($images_config['watermark']['enabled'] ?? false); ?>>
            <label for="image_watermark"><?php _e('فعال‌سازی واترمارک', 'forooshyar'); ?></label>
            
            <div id="watermark_settings" style="margin-top: 15px; <?php echo ($images_config['watermark']['enabled'] ?? false) ? '' : 'display: none;'; ?>">
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th style="width: 150px;">
                            <label for="watermark_image"><?php _e('تصویر واترمارک', 'forooshyar'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="watermark_image" name="images[watermark][image]" 
                                   value="<?php echo esc_attr($images_config['watermark']['image'] ?? ''); ?>" 
                                   class="regular-text">
                            <button type="button" id="select_watermark" class="button">
                                <?php _e('انتخاب', 'forooshyar'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="watermark_position"><?php _e('موقعیت', 'forooshyar'); ?></label>
                        </th>
                        <td>
                            <select id="watermark_position" name="images[watermark][position]" class="regular-text">
                                <option value="bottom-right" <?php selected($images_config['watermark']['position'] ?? 'bottom-right', 'bottom-right'); ?>>
                                    <?php _e('پایین راست', 'forooshyar'); ?>
                                </option>
                                <option value="bottom-left" <?php selected($images_config['watermark']['position'] ?? 'bottom-right', 'bottom-left'); ?>>
                                    <?php _e('پایین چپ', 'forooshyar'); ?>
                                </option>
                                <option value="top-right" <?php selected($images_config['watermark']['position'] ?? 'bottom-right', 'top-right'); ?>>
                                    <?php _e('بالا راست', 'forooshyar'); ?>
                                </option>
                                <option value="top-left" <?php selected($images_config['watermark']['position'] ?? 'bottom-right', 'top-left'); ?>>
                                    <?php _e('بالا چپ', 'forooshyar'); ?>
                                </option>
                                <option value="center" <?php selected($images_config['watermark']['position'] ?? 'bottom-right', 'center'); ?>>
                                    <?php _e('وسط', 'forooshyar'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="watermark_opacity"><?php _e('شفافیت', 'forooshyar'); ?></label>
                        </th>
                        <td>
                            <input type="range" id="watermark_opacity" name="images[watermark][opacity]" 
                                   value="<?php echo esc_attr($images_config['watermark']['opacity'] ?? 50); ?>" 
                                   min="10" max="100" step="5" class="forooshyar-range-input">
                            <span id="opacity_value" class="forooshyar-range-value"><?php echo esc_html($images_config['watermark']['opacity'] ?? 50); ?>%</span>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<style>
.forooshyar-range-input {
    width: 200px;
    margin-left: 10px;
}

.forooshyar-range-value {
    display: inline-block;
    min-width: 40px;
    text-align: center;
    font-weight: bold;
    color: #1e73be;
}

#watermark_settings {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

#watermark_settings .form-table th {
    padding-left: 0;
    padding-right: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update quality value display
    $('#image_quality').on('input', function() {
        $('#quality_value').text($(this).val() + '%');
    });
    
    // Update watermark opacity display
    $('#watermark_opacity').on('input', function() {
        $('#opacity_value').text($(this).val() + '%');
    });
    
    // Toggle watermark settings
    $('#image_watermark').on('change', function() {
        if ($(this).is(':checked')) {
            $('#watermark_settings').slideDown();
        } else {
            $('#watermark_settings').slideUp();
        }
    });
    
    // Media uploader for placeholder image
    $('#select_placeholder').on('click', function(e) {
        e.preventDefault();
        
        var mediaUploader = wp.media({
            title: '<?php _e("انتخاب تصویر جایگزین", "forooshyar"); ?>',
            button: {
                text: '<?php _e("انتخاب", "forooshyar"); ?>'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#placeholder_image').val(attachment.url);
        });
        
        mediaUploader.open();
    });
    
    // Media uploader for watermark image
    $('#select_watermark').on('click', function(e) {
        e.preventDefault();
        
        var mediaUploader = wp.media({
            title: '<?php _e("انتخاب تصویر واترمارک", "forooshyar"); ?>',
            button: {
                text: '<?php _e("انتخاب", "forooshyar"); ?>'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#watermark_image').val(attachment.url);
        });
        
        mediaUploader.open();
    });
});
</script>