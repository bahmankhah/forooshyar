<?php
/**
 * Cache Settings Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$cache_config = $config['cache'] ?? [];
?>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="cache_enabled"><?php _e('فعال‌سازی کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="cache_enabled" name="cache[enabled]" value="1" 
                   <?php checked($cache_config['enabled'] ?? true); ?>>
            <p class="forooshyar-field-description">
                <?php _e('فعال‌سازی سیستم کش برای بهبود عملکرد API', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cache_ttl"><?php _e('مدت زمان کش (ثانیه)', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="number" id="cache_ttl" name="cache[ttl]" 
                   value="<?php echo esc_attr($cache_config['ttl'] ?? 3600); ?>" 
                   min="60" max="86400" class="regular-text">
            <p class="forooshyar-field-description">
                <?php _e('مدت زمان نگهداری داده‌ها در کش (بین 60 ثانیه تا 24 ساعت)', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="auto_invalidate"><?php _e('پاک‌سازی خودکار کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="auto_invalidate" name="cache[auto_invalidate]" value="1" 
                   <?php checked($cache_config['auto_invalidate'] ?? true); ?>>
            <p class="forooshyar-field-description">
                <?php _e('پاک‌سازی خودکار کش هنگام تغییر محصولات', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cache_storage"><?php _e('نوع ذخیره‌سازی کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <select id="cache_storage" name="cache[storage]" class="regular-text">
                <option value="transient" <?php selected($cache_config['storage'] ?? 'transient', 'transient'); ?>>
                    <?php _e('WordPress Transient', 'forooshyar'); ?>
                </option>
                <option value="object" <?php selected($cache_config['storage'] ?? 'transient', 'object'); ?>>
                    <?php _e('Object Cache', 'forooshyar'); ?>
                </option>
                <option value="file" <?php selected($cache_config['storage'] ?? 'transient', 'file'); ?>>
                    <?php _e('File Cache', 'forooshyar'); ?>
                </option>
            </select>
            <p class="forooshyar-field-description">
                <?php _e('نوع سیستم ذخیره‌سازی کش. Transient برای اکثر سایت‌ها مناسب است.', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cache_compression"><?php _e('فشرده‌سازی کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="cache_compression" name="cache[compression]" value="1" 
                   <?php checked($cache_config['compression'] ?? false); ?>>
            <p class="forooshyar-field-description">
                <?php _e('فشرده‌سازی داده‌های کش برای صرفه‌جویی در فضای ذخیره‌سازی', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cache_prefix"><?php _e('پیشوند کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="text" id="cache_prefix" name="cache[prefix]" 
                   value="<?php echo esc_attr($cache_config['prefix'] ?? 'forooshyar_'); ?>" 
                   class="regular-text">
            <p class="forooshyar-field-description">
                <?php _e('پیشوند برای کلیدهای کش جهت جلوگیری از تداخل', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cache_invalidation_events"><?php _e('رویدادهای پاک‌سازی کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <fieldset>
                <legend class="screen-reader-text">
                    <span><?php _e('انتخاب رویدادهای پاک‌سازی کش', 'forooshyar'); ?></span>
                </legend>
                
                <label>
                    <input type="checkbox" name="cache[invalidation_events][]" value="product_save" 
                           <?php checked(in_array('product_save', $cache_config['invalidation_events'] ?? ['product_save', 'product_delete', 'variation_save'])); ?>>
                    <?php _e('ذخیره محصول', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="cache[invalidation_events][]" value="product_delete" 
                           <?php checked(in_array('product_delete', $cache_config['invalidation_events'] ?? ['product_save', 'product_delete', 'variation_save'])); ?>>
                    <?php _e('حذف محصول', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="cache[invalidation_events][]" value="variation_save" 
                           <?php checked(in_array('variation_save', $cache_config['invalidation_events'] ?? ['product_save', 'product_delete', 'variation_save'])); ?>>
                    <?php _e('ذخیره تنوع محصول', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="cache[invalidation_events][]" value="category_update" 
                           <?php checked(in_array('category_update', $cache_config['invalidation_events'] ?? [])); ?>>
                    <?php _e('بروزرسانی دسته‌بندی', 'forooshyar'); ?>
                </label><br>
                
                <label>
                    <input type="checkbox" name="cache[invalidation_events][]" value="stock_change" 
                           <?php checked(in_array('stock_change', $cache_config['invalidation_events'] ?? [])); ?>>
                    <?php _e('تغییر موجودی', 'forooshyar'); ?>
                </label>
            </fieldset>
            <p class="forooshyar-field-description">
                <?php _e('رویدادهایی که باعث پاک‌سازی خودکار کش می‌شوند', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="cache_warmup"><?php _e('گرم کردن کش', 'forooshyar'); ?></label>
        </th>
        <td>
            <input type="checkbox" id="cache_warmup" name="cache[warmup]" value="1" 
                   <?php checked($cache_config['warmup'] ?? false); ?>>
            <p class="forooshyar-field-description">
                <?php _e('بازسازی خودکار کش پس از پاک‌سازی', 'forooshyar'); ?>
            </p>
        </td>
    </tr>
</table>

<div class="forooshyar-cache-actions">
    <h4><?php _e('عملیات کش', 'forooshyar'); ?></h4>
    
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('مدیریت کش', 'forooshyar'); ?></th>
            <td>
                <button type="button" id="clear-all-cache" class="button">
                    <?php _e('پاک کردن تمام کش', 'forooshyar'); ?>
                </button>
                <button type="button" id="clear-product-cache" class="button">
                    <?php _e('پاک کردن کش محصولات', 'forooshyar'); ?>
                </button>
                <button type="button" id="warmup-cache" class="button">
                    <?php _e('گرم کردن کش', 'forooshyar'); ?>
                </button>
                <p class="forooshyar-field-description">
                    <?php _e('عملیات دستی برای مدیریت کش', 'forooshyar'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('آمار کش', 'forooshyar'); ?></th>
            <td>
                <div id="cache-stats" class="forooshyar-cache-stats">
                    <div class="forooshyar-stat-item">
                        <strong><?php _e('تعداد کلیدهای کش:', 'forooshyar'); ?></strong>
                        <span id="cache-keys-count">-</span>
                    </div>
                    <div class="forooshyar-stat-item">
                        <strong><?php _e('اندازه کش:', 'forooshyar'); ?></strong>
                        <span id="cache-size">-</span>
                    </div>
                    <div class="forooshyar-stat-item">
                        <strong><?php _e('نرخ موفقیت:', 'forooshyar'); ?></strong>
                        <span id="cache-hit-rate">-</span>
                    </div>
                </div>
                <button type="button" id="refresh-cache-stats" class="button button-small">
                    <?php _e('بروزرسانی آمار', 'forooshyar'); ?>
                </button>
            </td>
        </tr>
    </table>
</div>

<style>
.forooshyar-cache-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.forooshyar-cache-actions h4 {
    color: #1e73be;
    margin-bottom: 15px;
}

.forooshyar-cache-stats {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.forooshyar-stat-item {
    display: block;
    margin-bottom: 8px;
}

.forooshyar-stat-item:last-child {
    margin-bottom: 0;
}

.forooshyar-cache-actions .button {
    margin-left: 10px;
    margin-bottom: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load cache stats on page load
    loadCacheStats();
    
    // Clear all cache
    $('#clear-all-cache').on('click', function() {
        if (confirm('<?php _e("آیا مطمئن هستید که می‌خواهید تمام کش را پاک کنید؟", "forooshyar"); ?>')) {
            performCacheAction('clear_all');
        }
    });
    
    // Clear product cache
    $('#clear-product-cache').on('click', function() {
        if (confirm('<?php _e("آیا مطمئن هستید که می‌خواهید کش محصولات را پاک کنید؟", "forooshyar"); ?>')) {
            performCacheAction('clear_products');
        }
    });
    
    // Warmup cache
    $('#warmup-cache').on('click', function() {
        performCacheAction('warmup');
    });
    
    // Refresh cache stats
    $('#refresh-cache-stats').on('click', function() {
        loadCacheStats();
    });
    
    function performCacheAction(action) {
        var $button = $('#' + action.replace('_', '-') + '-cache, #warmup-cache');
        $button.prop('disabled', true).text('<?php _e("در حال پردازش...", "forooshyar"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'forooshyar_cache_action',
                cache_action: action,
                nonce: '<?php echo wp_create_nonce("forooshyar_cache"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCacheStats();
                } else {
                    alert('<?php _e("خطا: ", "forooshyar"); ?>' + response.data.message);
                }
            },
            error: function() {
                alert('<?php _e("خطا در ارتباط با سرور", "forooshyar"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false);
                if (action === 'clear_all') {
                    $button.text('<?php _e("پاک کردن تمام کش", "forooshyar"); ?>');
                } else if (action === 'clear_products') {
                    $button.text('<?php _e("پاک کردن کش محصولات", "forooshyar"); ?>');
                } else if (action === 'warmup') {
                    $button.text('<?php _e("گرم کردن کش", "forooshyar"); ?>');
                }
            }
        });
    }
    
    function loadCacheStats() {
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'forooshyar_get_cache_stats'
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data;
                    $('#cache-keys-count').text(stats.keys_count || 0);
                    $('#cache-size').text(stats.size || '0 KB');
                    $('#cache-hit-rate').text((stats.hit_rate || 0) + '%');
                }
            }
        });
    }
});
</script>