<?php
/**
 * AI Agent Settings Tab
 * Persian interface for AI Sales Agent module configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

use WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;

// Get services
$aiSettings = Container::resolve(SettingsManager::class);
$aiSubscription = Container::resolve(SubscriptionManager::class);

$settingsBySection = $aiSettings->getBySection();
$sectionLabels = $aiSettings->getSectionLabels();
$subscriptionStatus = $aiSubscription->getSubscriptionStatus();

$currentTier = isset($subscriptionStatus['tier']) ? $subscriptionStatus['tier'] : 'free';
$tierName = isset($subscriptionStatus['tier_name']) ? $subscriptionStatus['tier_name'] : 'رایگان';
?>

<div class="aiagent-settings-wrapper">
    <!-- Subscription Status Banner -->
    <div class="aiagent-subscription-banner">
        <div class="subscription-info">
            <span class="tier-badge tier-<?php echo esc_attr($currentTier); ?>">
                <?php echo esc_html($tierName); ?>
            </span>
            <span class="subscription-text">
                <?php printf(__('اشتراک فعلی: %s', 'forooshyar'), esc_html($tierName)); ?>
            </span>
        </div>
        <?php if ($currentTier !== 'enterprise'): ?>
        <a href="#upgrade" class="button button-primary"><?php _e('ارتقای پلن', 'forooshyar'); ?></a>
        <?php endif; ?>
    </div>

    <!-- Sub-tabs for AI Agent sections -->
    <div class="aiagent-sub-tabs">
        <?php 
        $firstSection = true;
        foreach ($sectionLabels as $key => $label): 
        ?>
        <button type="button" 
                class="aiagent-sub-tab <?php echo $firstSection ? 'active' : ''; ?>"
                data-section="<?php echo esc_attr($key); ?>">
            <?php echo esc_html($label); ?>
        </button>
        <?php 
        $firstSection = false;
        endforeach; 
        ?>
    </div>

    <!-- Section Contents -->
    <?php 
    $firstSection = true;
    foreach ($sectionLabels as $sectionKey => $sectionLabel): 
    ?>
    <div id="aiagent-section-<?php echo esc_attr($sectionKey); ?>" 
         class="aiagent-section" 
         <?php echo !$firstSection ? 'style="display:none;"' : ''; ?>>
        
        <h3><?php echo esc_html($sectionLabel); ?></h3>
        
        <?php if ($sectionKey === 'general'): ?>
        <p class="description">
            <?php _e('تنظیمات عمومی ماژول و فعال/غیرفعال کردن دستیار هوش مصنوعی.', 'forooshyar'); ?>
        </p>
        <?php elseif ($sectionKey === 'llm'): ?>
        <p class="description">
            <?php _e('پیکربندی اتصال به ارائه‌دهنده مدل زبانی. پس از تغییرات، اتصال را تست کنید.', 'forooshyar'); ?>
        </p>
        <?php endif; ?>

        <table class="form-table">
            <?php
            if (isset($settingsBySection[$sectionKey])):
                foreach ($settingsBySection[$sectionKey] as $key => $config):
                    $value = isset($config['value']) ? $config['value'] : $config['default'];
                    $type = isset($config['type']) ? $config['type'] : 'text';
                    $label = isset($config['label']) ? $config['label'] : $key;
                    $description = isset($config['description']) ? $config['description'] : '';
                    $requiresFeature = isset($config['requires_feature']) ? $config['requires_feature'] : null;
                    $featureEnabled = true;
                    
                    if ($requiresFeature && isset($subscriptionStatus['features'])) {
                        $featureEnabled = in_array($requiresFeature, $subscriptionStatus['features']);
                    }
            ?>
            <tr class="<?php echo !$featureEnabled ? 'feature-disabled' : ''; ?>">
                <th scope="row">
                    <label for="aiagent_<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php if ($requiresFeature && !$featureEnabled): ?>
                    <span class="feature-badge" title="<?php esc_attr_e('نیاز به ارتقا', 'forooshyar'); ?>">
                        <?php _e('حرفه‌ای', 'forooshyar'); ?>
                    </span>
                    <?php endif; ?>
                </th>
                <td>
                    <?php
                    $disabled = !$featureEnabled ? 'disabled' : '';
                    $fieldName = 'aiagent_' . $key;
                    
                    switch ($type):
                        case 'boolean':
                    ?>
                        <label class="forooshyar-toggle">
                            <input type="checkbox" 
                                   name="<?php echo esc_attr($fieldName); ?>" 
                                   id="<?php echo esc_attr($fieldName); ?>" 
                                   value="1" 
                                   <?php checked($value, true); ?> 
                                   <?php echo $disabled; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    <?php
                            break;
                        case 'select':
                    ?>
                        <select name="<?php echo esc_attr($fieldName); ?>" 
                                id="<?php echo esc_attr($fieldName); ?>" 
                                <?php echo $disabled; ?>>
                            <?php 
                            $options = isset($config['options']) ? $config['options'] : [];
                            foreach ($options as $optKey => $optLabel): 
                                $optValue = is_numeric($optKey) ? $optLabel : $optKey;
                                $optDisplay = is_numeric($optKey) ? $optLabel : $optLabel;
                            ?>
                            <option value="<?php echo esc_attr($optValue); ?>" <?php selected($value, $optValue); ?>>
                                <?php echo esc_html($optDisplay); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php
                            break;
                        case 'multiselect':
                    ?>
                        <select name="<?php echo esc_attr($fieldName); ?>[]" 
                                id="<?php echo esc_attr($fieldName); ?>" 
                                multiple 
                                class="aiagent-multiselect"
                                style="min-width: 300px; min-height: 100px;"
                                <?php echo $disabled; ?>>
                            <?php 
                            $currentValues = is_array($value) ? $value : [];
                            $options = isset($config['options']) ? $config['options'] : [];
                            foreach ($options as $optKey => $optLabel): 
                                $optValue = is_numeric($optKey) ? $optLabel : $optKey;
                                $optDisplay = is_numeric($optKey) ? sprintf('%02d:00', $optLabel) : $optLabel;
                            ?>
                            <option value="<?php echo esc_attr($optValue); ?>" 
                                    <?php echo in_array($optValue, $currentValues) ? 'selected' : ''; ?>>
                                <?php echo esc_html($optDisplay); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php
                            break;
                        case 'number':
                    ?>
                        <input type="number" 
                               name="<?php echo esc_attr($fieldName); ?>" 
                               id="<?php echo esc_attr($fieldName); ?>" 
                               value="<?php echo esc_attr($value); ?>"
                               class="small-text"
                               <?php echo isset($config['min']) ? 'min="' . esc_attr($config['min']) . '"' : ''; ?>
                               <?php echo isset($config['max']) ? 'max="' . esc_attr($config['max']) . '"' : ''; ?>
                               <?php echo isset($config['step']) ? 'step="' . esc_attr($config['step']) . '"' : ''; ?>
                               <?php echo $disabled; ?>>
                    <?php
                            break;
                        case 'password':
                    ?>
                        <input type="password" 
                               name="<?php echo esc_attr($fieldName); ?>" 
                               id="<?php echo esc_attr($fieldName); ?>" 
                               value="<?php echo esc_attr($value); ?>" 
                               class="regular-text"
                               autocomplete="new-password"
                               <?php echo $disabled; ?>>
                        <button type="button" class="button toggle-password" data-target="<?php echo esc_attr($fieldName); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    <?php
                            break;
                        case 'url':
                    ?>
                        <input type="url" 
                               name="<?php echo esc_attr($fieldName); ?>" 
                               id="<?php echo esc_attr($fieldName); ?>" 
                               value="<?php echo esc_url($value); ?>" 
                               class="regular-text"
                               <?php echo $disabled; ?>>
                    <?php
                            break;
                        case 'email':
                    ?>
                        <input type="email" 
                               name="<?php echo esc_attr($fieldName); ?>" 
                               id="<?php echo esc_attr($fieldName); ?>" 
                               value="<?php echo esc_attr($value); ?>" 
                               class="regular-text"
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                               <?php echo $disabled; ?>>
                    <?php
                            break;
                        default:
                    ?>
                        <input type="text" 
                               name="<?php echo esc_attr($fieldName); ?>" 
                               id="<?php echo esc_attr($fieldName); ?>" 
                               value="<?php echo esc_attr($value); ?>" 
                               class="regular-text"
                               <?php echo $disabled; ?>>
                    <?php
                            break;
                    endswitch;
                    
                    if (!empty($description)):
                    ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
                endforeach;
            endif;
            ?>
        </table>
    </div>
    <?php 
    $firstSection = false;
    endforeach; 
    ?>

    <!-- AI Agent specific actions -->
    <div class="aiagent-actions">
        <button type="button" class="button button-primary button-large" id="aiagent-save-settings">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('ذخیره تنظیمات دستیار هوشمند', 'forooshyar'); ?>
        </button>
        <span id="aiagent-save-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
        
        <button type="button" class="button" id="aiagent-test-connection">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('تست اتصال LLM', 'forooshyar'); ?>
        </button>
        
        <button type="button" class="button" id="aiagent-run-analysis">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php _e('اجرای تحلیل', 'forooshyar'); ?>
        </button>
        
        <button type="button" class="button" id="aiagent-reset-settings">
            <span class="dashicons dashicons-image-rotate"></span>
            <?php _e('بازگردانی به پیش‌فرض', 'forooshyar'); ?>
        </button>
    </div>

    <!-- Connection Test Result -->
    <div id="aiagent-connection-result" class="aiagent-notice" style="display:none;"></div>
</div>

<style>
.aiagent-settings-wrapper {
    margin-top: 20px;
}

.aiagent-subscription-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.aiagent-subscription-banner .subscription-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.aiagent-subscription-banner .tier-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.aiagent-subscription-banner .tier-badge.tier-free { background: #6c757d; }
.aiagent-subscription-banner .tier-badge.tier-basic { background: #28a745; }
.aiagent-subscription-banner .tier-badge.tier-pro { background: #007bff; }
.aiagent-subscription-banner .tier-badge.tier-enterprise { background: #fd7e14; }

.aiagent-sub-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 1px solid #ccd0d4;
    padding-bottom: 10px;
}

.aiagent-sub-tab {
    padding: 8px 16px;
    border: 1px solid #ccd0d4;
    background: #f0f0f1;
    cursor: pointer;
    border-radius: 4px 4px 0 0;
    transition: all 0.2s;
}

.aiagent-sub-tab:hover {
    background: #e0e0e0;
}

.aiagent-sub-tab.active {
    background: #fff;
    border-bottom-color: #fff;
    font-weight: bold;
}

.aiagent-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.aiagent-section h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.feature-disabled {
    opacity: 0.6;
}

.feature-badge {
    background: #007bff;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    margin-right: 5px;
}

.aiagent-actions {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.aiagent-actions .button .dashicons {
    margin-left: 5px;
    vertical-align: middle;
}

.aiagent-actions .button-primary {
    display: flex;
    align-items: center;
}

.aiagent-actions .spinner {
    margin: 0 10px;
}

.aiagent-notice {
    margin-top: 15px;
    padding: 12px 15px;
    border-radius: 4px;
}

.aiagent-notice.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.aiagent-notice.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.forooshyar-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.forooshyar-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    right: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.forooshyar-toggle input:checked + .toggle-slider {
    background-color: #2196F3;
}

.forooshyar-toggle input:checked + .toggle-slider:before {
    transform: translateX(-26px);
}
</style>

<script>
jQuery(document).ready(function($) {
    // Sub-tab navigation
    $('.aiagent-sub-tab').on('click', function() {
        var section = $(this).data('section');
        
        $('.aiagent-sub-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.aiagent-section').hide();
        $('#aiagent-section-' + section).show();
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        var target = $(this).data('target');
        var input = $('#' + target);
        var icon = $(this).find('.dashicons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Save AI Agent settings
    $('#aiagent-save-settings').on('click', function() {
        var $btn = $(this);
        var $spinner = $('#aiagent-save-spinner');
        var originalText = $btn.html();
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        // Collect all aiagent_ prefixed fields
        var settings = {};
        $('.aiagent-settings-wrapper').find('input, select, textarea').each(function() {
            var $el = $(this);
            var name = $el.attr('name');
            if (!name || !name.startsWith('aiagent_') || $el.prop('disabled')) return;
            
            // Remove aiagent_ prefix for the key
            var key = name.replace('aiagent_', '').replace('[]', '');
            
            if ($el.attr('type') === 'checkbox') {
                settings[key] = $el.is(':checked') ? '1' : '0';
            } else if ($el.is('select[multiple]')) {
                settings[key] = $el.val() || [];
            } else {
                settings[key] = $el.val();
            }
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_save_settings',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>',
                settings: settings
            },
            success: function(response) {
                var $result = $('#aiagent-connection-result');
                if (response.success) {
                    $result.removeClass('error').addClass('success').html('✓ ' + (response.data.message || '<?php _e('تنظیمات با موفقیت ذخیره شد', 'forooshyar'); ?>')).show();
                } else {
                    $result.removeClass('success').addClass('error').html('✗ ' + (response.data.message || '<?php _e('خطا در ذخیره تنظیمات', 'forooshyar'); ?>')).show();
                }
            },
            error: function() {
                $('#aiagent-connection-result').removeClass('success').addClass('error').html('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Reset AI Agent settings
    $('#aiagent-reset-settings').on('click', function() {
        if (!confirm('<?php _e('آیا مطمئن هستید که می‌خواهید تنظیمات دستیار هوشمند را به حالت پیش‌فرض بازگردانید؟', 'forooshyar'); ?>')) {
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_reset_settings',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#aiagent-connection-result').removeClass('error').addClass('success').html('✓ <?php _e('تنظیمات به حالت پیش‌فرض بازگردانده شد', 'forooshyar'); ?>').show();
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $('#aiagent-connection-result').removeClass('success').addClass('error').html('✗ ' + (response.data.message || '<?php _e('خطا', 'forooshyar'); ?>')).show();
                }
            },
            error: function() {
                $('#aiagent-connection-result').removeClass('success').addClass('error').html('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Test LLM connection
    $('#aiagent-test-connection').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span> <?php _e('در حال تست...', 'forooshyar'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_test_connection',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>'
            },
            success: function(response) {
                var $result = $('#aiagent-connection-result');
                if (response.success && response.data.success) {
                    $result.removeClass('error').addClass('success').html('✓ ' + response.data.message).show();
                } else {
                    $result.removeClass('success').addClass('error').html('✗ ' + (response.data.message || '<?php _e('خطا در اتصال', 'forooshyar'); ?>')).show();
                }
            },
            error: function() {
                $('#aiagent-connection-result').removeClass('success').addClass('error').html('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Run analysis
    $('#aiagent-run-analysis').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span> <?php _e('در حال تحلیل...', 'forooshyar'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_run_analysis',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>',
                type: 'all'
            },
            success: function(response) {
                var $result = $('#aiagent-connection-result');
                if (response.success) {
                    $result.removeClass('error').addClass('success').html('✓ <?php _e('تحلیل با موفقیت انجام شد', 'forooshyar'); ?>').show();
                } else {
                    $result.removeClass('success').addClass('error').html('✗ ' + (response.data.message || '<?php _e('خطا در تحلیل', 'forooshyar'); ?>')).show();
                }
            },
            error: function() {
                $('#aiagent-connection-result').removeClass('success').addClass('error').html('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
