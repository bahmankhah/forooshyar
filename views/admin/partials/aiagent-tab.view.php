<?php
/**
 * AI Agent Settings Tab
 * Persian interface for AI Sales Agent module configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

use Forooshyar\WPLite\Container;
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
                        <div class="aiagent-checkbox-group" id="<?php echo esc_attr($fieldName); ?>_group">
                            <?php 
                            $currentValues = is_array($value) ? $value : [];
                            $options = isset($config['options']) ? $config['options'] : [];
                            foreach ($options as $optKey => $optLabel): 
                                $optValue = is_numeric($optKey) ? $optLabel : $optKey;
                                $optDisplay = is_numeric($optKey) ? sprintf('%02d:00', $optLabel) : $optLabel;
                                $checkboxId = $fieldName . '_' . $optValue;
                            ?>
                            <label class="aiagent-checkbox-item" for="<?php echo esc_attr($checkboxId); ?>">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($fieldName); ?>[]" 
                                       id="<?php echo esc_attr($checkboxId); ?>" 
                                       value="<?php echo esc_attr($optValue); ?>"
                                       <?php echo in_array($optValue, $currentValues) ? 'checked' : ''; ?>
                                       <?php echo $disabled; ?>>
                                <span class="checkbox-label"><?php echo esc_html($optDisplay); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
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
        
        <button type="button" class="button" id="aiagent-reset-settings">
            <span class="dashicons dashicons-image-rotate"></span>
            <?php _e('بازگردانی به پیش‌فرض', 'forooshyar'); ?>
        </button>
    </div>

    <!-- Analysis Job Section -->
    <div class="aiagent-analysis-section">
        <h3><?php _e('اجرای تحلیل', 'forooshyar'); ?></h3>
        
        <div id="aiagent-analysis-idle" class="aiagent-analysis-state">
            <p class="description"><?php _e('تحلیل محصولات و مشتریان را شروع کنید. این فرآیند در پس‌زمینه اجرا می‌شود.', 'forooshyar'); ?></p>
            <button type="button" class="button button-primary" id="aiagent-start-analysis">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('شروع تحلیل', 'forooshyar'); ?>
            </button>
        </div>
        
        <div id="aiagent-analysis-running" class="aiagent-analysis-state" style="display:none;">
            <div class="aiagent-progress-header">
                <span class="aiagent-progress-status"><?php _e('در حال تحلیل...', 'forooshyar'); ?></span>
                <span class="aiagent-progress-percent">0%</span>
            </div>
            <div class="aiagent-progress-bar">
                <div class="aiagent-progress-fill" style="width: 0%"></div>
            </div>
            <div class="aiagent-progress-details">
                <span id="aiagent-progress-products"><?php _e('محصولات:', 'forooshyar'); ?> 0/0</span>
                <span id="aiagent-progress-customers"><?php _e('مشتریان:', 'forooshyar'); ?> 0/0</span>
                <span id="aiagent-progress-actions"><?php _e('اقدامات:', 'forooshyar'); ?> 0</span>
            </div>
            <div class="aiagent-progress-current" id="aiagent-current-item"></div>
            <button type="button" class="button button-secondary" id="aiagent-cancel-analysis">
                <span class="dashicons dashicons-no"></span>
                <?php _e('لغو تحلیل', 'forooshyar'); ?>
            </button>
        </div>
        
        <div id="aiagent-analysis-completed" class="aiagent-analysis-state" style="display:none;">
            <div class="aiagent-analysis-result"></div>
            <button type="button" class="button" id="aiagent-new-analysis">
                <span class="dashicons dashicons-update"></span>
                <?php _e('تحلیل جدید', 'forooshyar'); ?>
            </button>
        </div>
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

.aiagent-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-width: 600px;
}

.aiagent-checkbox-item {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #f0f0f1;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    margin: 0;
}

.aiagent-checkbox-item:hover {
    background: #e0e0e0;
    border-color: #999;
}

.aiagent-checkbox-item input[type="checkbox"] {
    margin: 0 8px 0 0;
}

.aiagent-checkbox-item input[type="checkbox"]:checked + .checkbox-label {
    font-weight: 600;
}

.aiagent-checkbox-item:has(input:checked) {
    background: #e7f3ff;
    border-color: #2196F3;
}

.aiagent-checkbox-item .checkbox-label {
    font-size: 13px;
    white-space: nowrap;
}

/* Analysis Section Styles */
.aiagent-analysis-section {
    margin-top: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.aiagent-analysis-section h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.aiagent-analysis-state {
    margin-top: 15px;
}

.aiagent-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.aiagent-progress-status {
    font-weight: bold;
    color: #2271b1;
}

.aiagent-progress-percent {
    font-size: 18px;
    font-weight: bold;
    color: #1d2327;
}

.aiagent-progress-bar {
    height: 24px;
    background: #f0f0f1;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 15px;
}

.aiagent-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    border-radius: 12px;
    transition: width 0.3s ease;
}

.aiagent-progress-details {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 10px;
    font-size: 13px;
    color: #646970;
}

.aiagent-progress-current {
    font-size: 12px;
    color: #888;
    margin-bottom: 15px;
    min-height: 18px;
}

.aiagent-analysis-result {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.aiagent-analysis-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.aiagent-analysis-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.aiagent-analysis-result.cancelled {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
}

#aiagent-cancel-analysis {
    color: #d63638;
    border-color: #d63638;
}

#aiagent-cancel-analysis:hover {
    background: #d63638;
    color: #fff;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Analysis progress polling
    var analysisPollingInterval = null;
    
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
                // Check if it's part of a checkbox group (multiselect)
                if (name.endsWith('[]')) {
                    if (!settings[key]) {
                        settings[key] = [];
                    }
                    if ($el.is(':checked')) {
                        settings[key].push($el.val());
                    }
                } else {
                    settings[key] = $el.is(':checked') ? '1' : '0';
                }
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

    // ===== Async Analysis Functions =====
    
    function showAnalysisState(state) {
        $('#aiagent-analysis-idle, #aiagent-analysis-running, #aiagent-analysis-completed').hide();
        $('#aiagent-analysis-' + state).show();
    }
    
    function updateProgressUI(progress) {
        $('.aiagent-progress-percent').text(progress.percentage + '%');
        $('.aiagent-progress-fill').css('width', progress.percentage + '%');
        
        $('#aiagent-progress-products').text('<?php _e('محصولات:', 'forooshyar'); ?> ' + progress.products.processed + '/' + progress.products.total);
        $('#aiagent-progress-customers').text('<?php _e('مشتریان:', 'forooshyar'); ?> ' + progress.customers.processed + '/' + progress.customers.total);
        $('#aiagent-progress-actions').text('<?php _e('اقدامات:', 'forooshyar'); ?> ' + progress.actions_created);
        
        if (progress.current_item) {
            var itemType = progress.current_item.type === 'product' ? '<?php _e('محصول', 'forooshyar'); ?>' : '<?php _e('مشتری', 'forooshyar'); ?>';
            $('#aiagent-current-item').text('<?php _e('در حال تحلیل:', 'forooshyar'); ?> ' + itemType + ' #' + progress.current_item.id);
        } else {
            $('#aiagent-current-item').text('');
        }
        
        if (progress.is_cancelling) {
            $('.aiagent-progress-status').text('<?php _e('در حال لغو...', 'forooshyar'); ?>');
            $('#aiagent-cancel-analysis').prop('disabled', true);
        }
    }
    
    function showCompletedResult(progress) {
        var $result = $('.aiagent-analysis-result');
        var totalSuccess = progress.products.success + progress.customers.success;
        var totalFailed = progress.products.failed + progress.customers.failed;
        
        var message = '';
        var resultClass = '';
        
        if (progress.status === 'completed') {
            resultClass = 'success';
            message = '<?php _e('تحلیل با موفقیت انجام شد.', 'forooshyar'); ?><br>';
            message += '<?php _e('محصولات:', 'forooshyar'); ?> ' + progress.products.success + '/' + progress.products.total + ' ';
            message += '<?php _e('مشتریان:', 'forooshyar'); ?> ' + progress.customers.success + '/' + progress.customers.total + ' ';
            message += '<?php _e('اقدامات ایجاد شده:', 'forooshyar'); ?> ' + progress.actions_created;
            
            if (totalFailed > 0) {
                message += '<br><small style="color:#856404;"><?php _e('تعداد خطاها:', 'forooshyar'); ?> ' + totalFailed + '</small>';
            }
        } else if (progress.status === 'cancelled') {
            resultClass = 'cancelled';
            message = '<?php _e('تحلیل لغو شد.', 'forooshyar'); ?><br>';
            message += '<?php _e('تحلیل شده:', 'forooshyar'); ?> ' + (progress.products.processed + progress.customers.processed);
        } else if (progress.status === 'failed') {
            resultClass = 'error';
            message = '<?php _e('تحلیل با خطا مواجه شد.', 'forooshyar'); ?>';
            if (progress.errors && progress.errors.length > 0) {
                message += '<br><small>' + progress.errors.map(function(e) { return e.error; }).join('<br>') + '</small>';
            }
        }
        
        $result.removeClass('success error cancelled').addClass(resultClass).html(message);
        showAnalysisState('completed');
    }
    
    function pollAnalysisProgress() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_get_analysis_progress',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var progress = response.data;
                    
                    if (progress.is_running || progress.is_cancelling) {
                        updateProgressUI(progress);
                    } else if (progress.status === 'completed' || progress.status === 'cancelled' || progress.status === 'failed') {
                        stopPolling();
                        showCompletedResult(progress);
                    } else {
                        // Idle state
                        stopPolling();
                        showAnalysisState('idle');
                    }
                }
            },
            error: function() {
                // Continue polling on error
            }
        });
    }
    
    function startPolling() {
        if (analysisPollingInterval) return;
        analysisPollingInterval = setInterval(pollAnalysisProgress, 2000);
        pollAnalysisProgress(); // Immediate first poll
    }
    
    function stopPolling() {
        if (analysisPollingInterval) {
            clearInterval(analysisPollingInterval);
            analysisPollingInterval = null;
        }
    }
    
    // Start Analysis
    $('#aiagent-start-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_start_analysis',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>',
                type: 'all'
            },
            success: function(response) {
                if (response.success) {
                    showAnalysisState('running');
                    startPolling();
                } else {
                    $('#aiagent-connection-result').removeClass('success').addClass('error').html('✗ ' + (response.data.error || response.data.message || '<?php _e('خطا در شروع تحلیل', 'forooshyar'); ?>')).show();
                }
            },
            error: function(xhr) {
                var errorMsg = '<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data.message || xhr.responseJSON.data.error || errorMsg;
                }
                $('#aiagent-connection-result').removeClass('success').addClass('error').html('✗ ' + errorMsg).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Cancel Analysis
    $('#aiagent-cancel-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('.aiagent-progress-status').text('<?php _e('در حال لغو...', 'forooshyar'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiagent_cancel_analysis',
                nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>'
            },
            success: function(response) {
                // Polling will handle the state change
            },
            error: function() {
                $btn.prop('disabled', false);
                $('.aiagent-progress-status').text('<?php _e('در حال تحلیل...', 'forooshyar'); ?>');
            }
        });
    });
    
    // New Analysis (after completed)
    $('#aiagent-new-analysis').on('click', function() {
        showAnalysisState('idle');
    });
    
    // Check initial state on page load
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'aiagent_get_analysis_progress',
            nonce: '<?php echo wp_create_nonce('aiagent_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                var progress = response.data;
                if (progress.is_running || progress.is_cancelling) {
                    showAnalysisState('running');
                    updateProgressUI(progress);
                    startPolling();
                } else if (progress.status === 'completed' || progress.status === 'cancelled' || progress.status === 'failed') {
                    if (progress.completed_at || progress.status !== 'idle') {
                        showCompletedResult(progress);
                    }
                }
            }
        }
    });
});
</script>
