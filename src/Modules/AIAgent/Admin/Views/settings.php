<?php
/**
 * AI Agent Settings View
 * 
 * @var array $settingsBySection
 * @var array $sectionLabels
 * @var array $subscriptionStatus
 * @var array $featuresComparison
 */

if (!defined('ABSPATH')) exit;

$currentTier = isset($subscriptionStatus['tier']) ? $subscriptionStatus['tier'] : 'free';
$tierName = isset($subscriptionStatus['tier_name']) ? $subscriptionStatus['tier_name'] : 'Free';
?>
<div class="wrap aiagent-settings">
    <h1><?php _e('AI Agent Settings', 'forooshyar'); ?></h1>

    <!-- Subscription Status Banner -->
    <div class="aiagent-subscription-banner">
        <div class="subscription-info">
            <span class="tier-badge tier-<?php echo esc_attr($currentTier); ?>">
                <?php echo esc_html($tierName); ?>
            </span>
            <span class="subscription-text">
                <?php printf(__('Current Subscription: %s', 'forooshyar'), esc_html($tierName)); ?>
            </span>
        </div>
        <?php if ($currentTier !== 'enterprise'): ?>
        <a href="#upgrade" class="button button-primary"><?php _e('Upgrade Plan', 'forooshyar'); ?></a>
        <?php endif; ?>
    </div>

    <div class="aiagent-settings-container">
        <!-- Tabs Navigation -->
        <nav class="nav-tab-wrapper">
            <?php 
            $firstSection = true;
            foreach ($sectionLabels as $key => $label): 
            ?>
            <a href="#section-<?php echo esc_attr($key); ?>" 
               class="nav-tab <?php echo $firstSection ? 'nav-tab-active' : ''; ?>"
               data-section="<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </a>
            <?php 
            $firstSection = false;
            endforeach; 
            ?>
        </nav>

        <form id="aiagent-settings-form" method="post">
            <?php wp_nonce_field('aiagent_settings', 'aiagent_nonce'); ?>

            <?php 
            $firstSection = true;
            foreach ($sectionLabels as $sectionKey => $sectionLabel): 
            ?>
            <div id="section-<?php echo esc_attr($sectionKey); ?>" 
                 class="settings-section" 
                 <?php echo !$firstSection ? 'style="display:none;"' : ''; ?>>
                
                <h2><?php echo esc_html($sectionLabel); ?></h2>
                
                <?php if ($sectionKey === 'general'): ?>
                <p class="description">
                    <?php _e('Configure general module settings and enable/disable the AI Agent.', 'forooshyar'); ?>
                </p>
                <?php elseif ($sectionKey === 'llm'): ?>
                <p class="description">
                    <?php _e('Configure your LLM provider connection. Test the connection after making changes.', 'forooshyar'); ?>
                </p>
                <?php endif; ?>

                <table class="form-table">
                    <?php
                    if (isset($settingsBySection[$sectionKey])):
                        foreach ($settingsBySection[$sectionKey] as $key => $config):
                            $value = isset($config['value']) ? $config['value'] : $config['default'];
                            $type = isset($config['type']) ? $config['type'] : 'text';
                            $label = isset($config['label']) ? $config['label'] : $key;
                            $requiresFeature = isset($config['requires_feature']) ? $config['requires_feature'] : null;
                            $featureEnabled = true;
                            
                            if ($requiresFeature && isset($subscriptionStatus['features'])) {
                                $featureEnabled = in_array($requiresFeature, $subscriptionStatus['features']);
                            }
                    ?>
                    <tr class="<?php echo !$featureEnabled ? 'feature-disabled' : ''; ?>">
                        <th scope="row">
                            <label for="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php if ($requiresFeature && !$featureEnabled): ?>
                            <span class="feature-badge" title="<?php esc_attr_e('Requires upgrade', 'forooshyar'); ?>">
                                <?php _e('Pro', 'forooshyar'); ?>
                            </span>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?php
                            $disabled = !$featureEnabled ? 'disabled' : '';
                            
                            switch ($type):
                                case 'boolean':
                            ?>
                                <label class="aiagent-toggle">
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr($key); ?>" 
                                           id="<?php echo esc_attr($key); ?>" 
                                           value="1" 
                                           <?php checked($value, true); ?> 
                                           <?php echo $disabled; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php
                                    break;
                                case 'select':
                            ?>
                                <select name="<?php echo esc_attr($key); ?>" 
                                        id="<?php echo esc_attr($key); ?>" 
                                        <?php echo $disabled; ?>>
                                    <?php foreach ($config['options'] as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>>
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $option))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php
                                    break;
                                case 'multiselect':
                            ?>
                                <select name="<?php echo esc_attr($key); ?>[]" 
                                        id="<?php echo esc_attr($key); ?>" 
                                        multiple 
                                        class="aiagent-multiselect"
                                        <?php echo $disabled; ?>>
                                    <?php 
                                    $currentValues = is_array($value) ? $value : [];
                                    foreach ($config['options'] as $option): 
                                        $optLabel = is_numeric($option) ? sprintf('%02d:00', $option) : ucfirst(str_replace('_', ' ', $option));
                                    ?>
                                    <option value="<?php echo esc_attr($option); ?>" 
                                            <?php echo in_array($option, $currentValues) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($optLabel); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php
                                    break;
                                case 'number':
                            ?>
                                <input type="number" 
                                       name="<?php echo esc_attr($key); ?>" 
                                       id="<?php echo esc_attr($key); ?>" 
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
                                       name="<?php echo esc_attr($key); ?>" 
                                       id="<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_attr($value); ?>" 
                                       class="regular-text"
                                       autocomplete="new-password"
                                       <?php echo $disabled; ?>>
                                <button type="button" class="button toggle-password" data-target="<?php echo esc_attr($key); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            <?php
                                    break;
                                case 'url':
                            ?>
                                <input type="url" 
                                       name="<?php echo esc_attr($key); ?>" 
                                       id="<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_url($value); ?>" 
                                       class="regular-text"
                                       <?php echo $disabled; ?>>
                            <?php
                                    break;
                                case 'email':
                            ?>
                                <input type="email" 
                                       name="<?php echo esc_attr($key); ?>" 
                                       id="<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_attr($value); ?>" 
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                                       <?php echo $disabled; ?>>
                            <?php
                                    break;
                                default:
                            ?>
                                <input type="text" 
                                       name="<?php echo esc_attr($key); ?>" 
                                       id="<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_attr($value); ?>" 
                                       class="regular-text"
                                       <?php echo $disabled; ?>>
                            <?php
                                    break;
                            endswitch;
                            
                            if (isset($config['description'])):
                            ?>
                            <p class="description"><?php echo esc_html($config['description']); ?></p>
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

            <div class="aiagent-settings-footer">
                <div class="footer-actions">
                    <button type="submit" class="button button-primary" id="save-settings">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Settings', 'forooshyar'); ?>
                    </button>
                    
                    <button type="button" class="button" id="test-connection">
                        <span class="dashicons dashicons-admin-plugins"></span>
                        <?php _e('Test LLM Connection', 'forooshyar'); ?>
                    </button>
                    
                    <button type="button" class="button" id="reset-settings">
                        <span class="dashicons dashicons-image-rotate"></span>
                        <?php _e('Reset to Defaults', 'forooshyar'); ?>
                    </button>
                </div>
                
                <div class="footer-tools">
                    <button type="button" class="button" id="export-settings">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export', 'forooshyar'); ?>
                    </button>
                    
                    <button type="button" class="button" id="import-settings">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Import', 'forooshyar'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Import Modal -->
    <div id="import-modal" class="aiagent-modal" style="display:none;">
        <div class="modal-content">
            <h3><?php _e('Import Settings', 'forooshyar'); ?></h3>
            <p><?php _e('Paste your exported settings JSON below:', 'forooshyar'); ?></p>
            <textarea id="import-json" rows="10" class="large-text"></textarea>
            <div class="modal-actions">
                <button type="button" class="button button-primary" id="do-import">
                    <?php _e('Import', 'forooshyar'); ?>
                </button>
                <button type="button" class="button" id="cancel-import">
                    <?php _e('Cancel', 'forooshyar'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Connection Test Result -->
    <div id="connection-result" class="aiagent-notice" style="display:none;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var section = $(this).data('section');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.settings-section').hide();
        $('#section-' + section).show();
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

    // Save settings
    $('#aiagent-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $('#save-settings');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active"></span> <?php _e('Saving...', 'forooshyar'); ?>');

        var formData = {};
        $(this).find('input, select, textarea').each(function() {
            var $el = $(this);
            var name = $el.attr('name');
            if (!name || $el.prop('disabled')) return;
            
            name = name.replace('[]', '');
            
            if ($el.attr('type') === 'checkbox') {
                formData[name] = $el.is(':checked') ? '1' : '0';
            } else if ($el.is('select[multiple]')) {
                formData[name] = $el.val() || [];
            } else {
                formData[name] = $el.val();
            }
        });

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_save_settings',
                nonce: aiagentAdmin.nonce,
                settings: formData
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message || aiagentAdmin.strings.error);
                }
            },
            error: function() {
                showNotice('error', aiagentAdmin.strings.error);
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Test connection
    $('#test-connection').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active"></span> <?php _e('Testing...', 'forooshyar'); ?>');

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_test_connection',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.success) {
                    showNotice('success', '✓ ' + response.data.message);
                } else {
                    showNotice('error', '✗ ' + (response.data.message || aiagentAdmin.strings.error));
                }
            },
            error: function() {
                showNotice('error', aiagentAdmin.strings.error);
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Reset settings
    $('#reset-settings').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to reset all settings to defaults?', 'forooshyar'); ?>')) {
            return;
        }

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_reset_settings',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload();
                }
            }
        });
    });

    // Export settings
    $('#export-settings').on('click', function() {
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_export_settings',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var json = JSON.stringify(response.data, null, 2);
                    var blob = new Blob([json], {type: 'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'aiagent-settings-' + new Date().toISOString().slice(0,10) + '.json';
                    a.click();
                    URL.revokeObjectURL(url);
                }
            }
        });
    });

    // Import settings
    $('#import-settings').on('click', function() {
        $('#import-modal').show();
    });

    $('#cancel-import').on('click', function() {
        $('#import-modal').hide();
        $('#import-json').val('');
    });

    $('#do-import').on('click', function() {
        var json = $('#import-json').val();
        if (!json) {
            alert('<?php _e('Please paste settings JSON', 'forooshyar'); ?>');
            return;
        }

        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_import_settings',
                nonce: aiagentAdmin.nonce,
                settings_json: json
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $('#import-modal').hide();
                    location.reload();
                } else {
                    showNotice('error', response.data.message);
                }
            }
        });
    });

    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        setTimeout(function() { $notice.fadeOut(); }, 5000);
    }
});
</script>
