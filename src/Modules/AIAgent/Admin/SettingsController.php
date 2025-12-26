<?php
/**
 * Settings Controller
 * 
 * Handles admin settings page rendering and AJAX operations
 * 
 * @package Forooshyar\Modules\AIAgent\Admin
 */

namespace Forooshyar\Modules\AIAgent\Admin;

use Forooshyar\Modules\AIAgent\Services\SettingsManager;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;

class SettingsController
{
    /** @var SettingsManager */
    private $settings;

    /** @var SubscriptionManager */
    private $subscription;

    /**
     * @param SettingsManager $settings
     * @param SubscriptionManager $subscription
     */
    public function __construct(SettingsManager $settings, SubscriptionManager $subscription)
    {
        $this->settings = $settings;
        $this->subscription = $subscription;
    }

    /**
     * Register settings page hooks
     * Note: AJAX routes are now defined in routes/ajax.php using AIAgentController
     *
     * @return void
     */
    public function register()
    {
        add_action('admin_init', [$this, 'initSettings']);
        // AJAX routes are now registered via routes/ajax.php
        // See AIAgentController for handler implementations
    }

    /**
     * Initialize WordPress settings
     *
     * @return void
     */
    public function initSettings()
    {
        $this->settings->registerWordPressSettings();
    }

    /**
     * Render settings page
     * 
     * Note: AI Agent settings are now rendered as a tab in the main settings page
     * via views/admin/partials/aiagent-tab.view.php
     * This method is kept for backward compatibility but redirects to main settings
     *
     * @return void
     */
    public function render()
    {
        // Redirect to main settings page with aiagent tab
        wp_redirect(admin_url('admin.php?page=forooshyar&tab=aiagent'));
        exit;
    }

    /**
     * AJAX: Save settings
     *
     * @return void
     */
    public function ajaxSaveSettings()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $data = isset($_POST['settings']) ? $_POST['settings'] : [];
        
        if (empty($data)) {
            wp_send_json_error(['message' => __('تنظیماتی ارائه نشده', 'forooshyar')], 400);
        }

        $result = $this->settings->bulkUpdate($data);

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    __('%d تنظیم با موفقیت ذخیره شد', 'forooshyar'),
                    $result['updated']
                ),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('برخی تنظیمات ذخیره نشدند', 'forooshyar'),
                'errors' => $result['errors'],
            ], 400);
        }
    }

    /**
     * AJAX: Reset settings
     *
     * @return void
     */
    public function ajaxResetSettings()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $section = isset($_POST['section']) ? sanitize_key($_POST['section']) : null;
        
        if ($section) {
            // Reset only settings in specified section
            $settingsBySection = $this->settings->getBySection();
            if (isset($settingsBySection[$section])) {
                $keys = array_keys($settingsBySection[$section]);
                $this->settings->reset($keys);
            }
        } else {
            // Reset all settings
            $this->settings->reset();
        }

        wp_send_json_success([
            'message' => __('تنظیمات به حالت پیش‌فرض بازگردانده شد', 'forooshyar'),
        ]);
    }

    /**
     * AJAX: Export settings
     *
     * @return void
     */
    public function ajaxExportSettings()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $includeSecrets = isset($_POST['include_secrets']) && $_POST['include_secrets'] === 'true';
        $settings = $this->settings->export($includeSecrets);

        wp_send_json_success([
            'settings' => $settings,
            'exported_at' => current_time('mysql'),
            'version' => SettingsManager::VERSION,
        ]);
    }

    /**
     * AJAX: Import settings
     *
     * @return void
     */
    public function ajaxImportSettings()
    {
        check_ajax_referer('aiagent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jsonData = isset($_POST['settings_json']) ? wp_unslash($_POST['settings_json']) : '';
        
        if (empty($jsonData)) {
            wp_send_json_error(['message' => __('داده تنظیماتی ارائه نشده', 'forooshyar')], 400);
        }

        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('فرمت JSON نامعتبر', 'forooshyar')], 400);
        }

        $settings = isset($data['settings']) ? $data['settings'] : $data;
        $result = $this->settings->import($settings);

        if (empty($result['errors'])) {
            wp_send_json_success([
                'message' => sprintf(
                    __('%d تنظیم با موفقیت وارد شد', 'forooshyar'),
                    $result['imported']
                ),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('برخی تنظیمات وارد نشدند', 'forooshyar'),
                'imported' => $result['imported'],
                'errors' => $result['errors'],
            ], 400);
        }
    }

    /**
     * Get settings data for JavaScript
     *
     * @return array
     */
    public function getJsData()
    {
        return [
            'settings' => $this->settings->all(),
            'schema' => $this->settings->getSchema(),
            'sections' => $this->settings->getSectionLabels(),
            'subscription' => $this->subscription->getSubscriptionStatus(),
        ];
    }
}
