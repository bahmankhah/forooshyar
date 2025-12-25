<?php
/**
 * Settings Manager Service
 * 
 * Handles all AI Agent module settings with:
 * - WordPress options table storage with prefix `aiagent_`
 * - Admin UI accessibility
 * - Validation before saving
 * - Sensible defaults
 * - Encryption for sensitive data
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

class SettingsManager
{
    const SETTINGS_GROUP = 'aiagent_settings';
    const OPTION_PREFIX = 'aiagent_';
    const VERSION = '1.0.0';

    /** @var array */
    private $schema;

    /** @var array */
    private $cache = [];

    /** @var array Section labels for admin UI */
    private $sectionLabels = [
        'general' => 'General Settings',
        'llm' => 'LLM Configuration',
        'analysis' => 'Analysis Settings',
        'actions' => 'Action Settings',
        'schedule' => 'Schedule Settings',
        'notifications' => 'Notification Settings',
        'rate_limiting' => 'Rate Limiting',
        'debug' => 'Debug Settings',
    ];

    public function __construct()
    {
        $this->loadSchema();
    }

    /**
     * Initialize WordPress settings registration
     * Call this during admin_init hook
     *
     * @return void
     */
    public function registerWordPressSettings()
    {
        // Register settings group
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PREFIX . 'settings',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeAllSettings'],
            ]
        );

        // Register sections
        foreach ($this->sectionLabels as $sectionId => $sectionLabel) {
            add_settings_section(
                self::SETTINGS_GROUP . '_' . $sectionId,
                $sectionLabel,
                [$this, 'renderSectionDescription'],
                self::SETTINGS_GROUP
            );
        }

        // Register fields
        foreach ($this->schema as $key => $config) {
            $section = isset($config['section']) ? $config['section'] : 'general';
            
            add_settings_field(
                self::OPTION_PREFIX . $key,
                isset($config['label']) ? $config['label'] : $key,
                [$this, 'renderField'],
                self::SETTINGS_GROUP,
                self::SETTINGS_GROUP . '_' . $section,
                [
                    'key' => $key,
                    'config' => $config,
                ]
            );
        }
    }

    /**
     * Render section description
     *
     * @param array $args
     * @return void
     */
    public function renderSectionDescription($args)
    {
        $sectionId = str_replace(self::SETTINGS_GROUP . '_', '', $args['id']);
        $descriptions = [
            'general' => 'Configure general module settings.',
            'llm' => 'Configure your LLM provider connection settings.',
            'analysis' => 'Configure how product and customer analysis works.',
            'actions' => 'Configure which actions are enabled and their behavior.',
            'schedule' => 'Configure when automated analysis runs.',
            'notifications' => 'Configure email notifications.',
            'rate_limiting' => 'Configure API rate limits to prevent overuse.',
            'debug' => 'Configure debugging and logging options.',
        ];
        
        if (isset($descriptions[$sectionId])) {
            echo '<p>' . esc_html($descriptions[$sectionId]) . '</p>';
        }
    }

    /**
     * Render a settings field
     *
     * @param array $args
     * @return void
     */
    public function renderField($args)
    {
        $key = $args['key'];
        $config = $args['config'];
        $value = $this->get($key);
        $type = isset($config['type']) ? $config['type'] : 'text';
        $fieldName = self::OPTION_PREFIX . $key;

        switch ($type) {
            case 'boolean':
                printf(
                    '<input type="checkbox" name="%s" id="%s" value="1" %s />',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    checked($value, true, false)
                );
                break;

            case 'select':
                printf('<select name="%s" id="%s">', esc_attr($fieldName), esc_attr($fieldName));
                foreach ($config['options'] as $option) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option),
                        selected($value, $option, false),
                        esc_html(ucfirst(str_replace('_', ' ', $option)))
                    );
                }
                echo '</select>';
                break;

            case 'multiselect':
                printf(
                    '<select name="%s[]" id="%s" multiple class="aiagent-multiselect" style="min-width:300px;min-height:100px;">',
                    esc_attr($fieldName),
                    esc_attr($fieldName)
                );
                $currentValues = is_array($value) ? $value : [];
                foreach ($config['options'] as $option) {
                    $optionLabel = is_numeric($option) ? sprintf('%02d:00', $option) : ucfirst(str_replace('_', ' ', $option));
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option),
                        in_array($option, $currentValues) ? 'selected' : '',
                        esc_html($optionLabel)
                    );
                }
                echo '</select>';
                break;

            case 'number':
                printf(
                    '<input type="number" name="%s" id="%s" value="%s" class="small-text" %s %s %s />',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    esc_attr($value),
                    isset($config['min']) ? 'min="' . esc_attr($config['min']) . '"' : '',
                    isset($config['max']) ? 'max="' . esc_attr($config['max']) . '"' : '',
                    isset($config['step']) ? 'step="' . esc_attr($config['step']) . '"' : ''
                );
                break;

            case 'password':
                printf(
                    '<input type="password" name="%s" id="%s" value="%s" class="regular-text" autocomplete="new-password" />',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    esc_attr($value)
                );
                break;

            case 'url':
                printf(
                    '<input type="url" name="%s" id="%s" value="%s" class="regular-text" />',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    esc_url($value)
                );
                break;

            case 'email':
                printf(
                    '<input type="email" name="%s" id="%s" value="%s" class="regular-text" />',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    esc_attr($value)
                );
                break;

            case 'textarea':
                printf(
                    '<textarea name="%s" id="%s" rows="5" class="large-text">%s</textarea>',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    esc_textarea($value)
                );
                break;

            default:
                printf(
                    '<input type="text" name="%s" id="%s" value="%s" class="regular-text" />',
                    esc_attr($fieldName),
                    esc_attr($fieldName),
                    esc_attr($value)
                );
        }

        // Add description if available
        if (isset($config['description'])) {
            printf('<p class="description">%s</p>', esc_html($config['description']));
        }

        // Add feature requirement notice
        if (isset($config['requires_feature'])) {
            printf(
                '<p class="description"><em>%s</em></p>',
                sprintf(
                    esc_html__('Requires %s feature', 'forooshyar'),
                    esc_html($config['requires_feature'])
                )
            );
        }
    }

    /**
     * Sanitize all settings from form submission
     *
     * @param array $input
     * @return array
     */
    public function sanitizeAllSettings($input)
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $cleanKey = str_replace(self::OPTION_PREFIX, '', $key);
            $validation = $this->validate($cleanKey, $value);
            
            if ($validation['valid']) {
                $sanitized[$cleanKey] = $this->sanitize($cleanKey, $value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Load settings schema from config
     *
     * @return void
     */
    private function loadSchema()
    {
        $configPath = dirname(__DIR__) . '/Config/ai-agent.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $this->schema = isset($config['settings_schema']) ? $config['settings_schema'] : [];
        } else {
            $this->schema = [];
        }
    }

    /**
     * Get setting value with default fallback
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $schemaDefault = $this->getSchemaDefault($key);
        $finalDefault = $default !== null ? $default : $schemaDefault;

        $value = get_option(self::OPTION_PREFIX . $key, $finalDefault);

        // Decrypt if needed
        if ($this->isEncrypted($key) && !empty($value)) {
            $value = $this->decrypt($value);
        }

        $this->cache[$key] = $value;
        return $value;
    }

    /**
     * Set setting value with validation
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set($key, $value)
    {
        $validation = $this->validate($key, $value);
        if (!$validation['valid']) {
            return false;
        }

        // Sanitize value
        $value = $this->sanitize($key, $value);

        // Encrypt if needed
        if ($this->isEncrypted($key) && !empty($value)) {
            $value = $this->encrypt($value);
        }

        $oldValue = $this->get($key);
        $result = update_option(self::OPTION_PREFIX . $key, $value);

        if ($result) {
            $this->cache[$key] = $value;
            do_action('aiagent_settings_updated', $key, $oldValue, $value);
        }

        return $result;
    }

    /**
     * Get all settings as array
     *
     * @return array
     */
    public function all()
    {
        $settings = [];
        foreach (array_keys($this->schema) as $key) {
            $settings[$key] = $this->get($key);
        }
        return $settings;
    }

    /**
     * Reset settings to defaults
     *
     * @param array|null $keys Specific keys or null for all
     * @return void
     */
    public function reset($keys = null)
    {
        $keysToReset = $keys !== null ? $keys : array_keys($this->schema);

        foreach ($keysToReset as $key) {
            $default = $this->getSchemaDefault($key);
            delete_option(self::OPTION_PREFIX . $key);
            unset($this->cache[$key]);
        }
    }

    /**
     * Validate setting value
     *
     * @param string $key
     * @param mixed $value
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validate($key, $value)
    {
        if (!isset($this->schema[$key])) {
            return ['valid' => true, 'error' => null];
        }

        $schema = $this->schema[$key];
        $type = isset($schema['type']) ? $schema['type'] : 'text';

        switch ($type) {
            case 'boolean':
                return ['valid' => true, 'error' => null];

            case 'number':
                if (!is_numeric($value)) {
                    return ['valid' => false, 'error' => 'Value must be a number'];
                }
                if (isset($schema['min']) && $value < $schema['min']) {
                    return ['valid' => false, 'error' => "Value must be at least {$schema['min']}"];
                }
                if (isset($schema['max']) && $value > $schema['max']) {
                    return ['valid' => false, 'error' => "Value must be at most {$schema['max']}"];
                }
                return ['valid' => true, 'error' => null];

            case 'select':
                $options = isset($schema['options']) ? $schema['options'] : [];
                if (!in_array($value, $options)) {
                    return ['valid' => false, 'error' => 'Invalid option selected'];
                }
                return ['valid' => true, 'error' => null];

            case 'multiselect':
                if (!is_array($value)) {
                    return ['valid' => false, 'error' => 'Value must be an array'];
                }
                $options = isset($schema['options']) ? $schema['options'] : [];
                foreach ($value as $v) {
                    if (!in_array($v, $options)) {
                        return ['valid' => false, 'error' => 'Invalid option in selection'];
                    }
                }
                return ['valid' => true, 'error' => null];

            case 'email':
                if (!empty($value) && !is_email($value)) {
                    return ['valid' => false, 'error' => 'Invalid email address'];
                }
                return ['valid' => true, 'error' => null];

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return ['valid' => false, 'error' => 'Invalid URL'];
                }
                return ['valid' => true, 'error' => null];

            default:
                return ['valid' => true, 'error' => null];
        }
    }

    /**
     * Get schema for a setting
     *
     * @param string $key
     * @return array|null
     */
    public function getSchema($key = null)
    {
        if ($key === null) {
            return $this->schema;
        }
        return isset($this->schema[$key]) ? $this->schema[$key] : null;
    }

    /**
     * Get settings grouped by section
     *
     * @return array
     */
    public function getBySection()
    {
        $sections = [];
        foreach ($this->schema as $key => $config) {
            $section = isset($config['section']) ? $config['section'] : 'general';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][$key] = array_merge($config, ['value' => $this->get($key)]);
        }
        return $sections;
    }

    /**
     * Get default value from schema
     *
     * @param string $key
     * @return mixed
     */
    private function getSchemaDefault($key)
    {
        if (isset($this->schema[$key]['default'])) {
            return $this->schema[$key]['default'];
        }
        return null;
    }

    /**
     * Check if setting should be encrypted
     *
     * @param string $key
     * @return bool
     */
    private function isEncrypted($key)
    {
        return isset($this->schema[$key]['encrypted']) && $this->schema[$key]['encrypted'];
    }

    /**
     * Sanitize value based on type
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    private function sanitize($key, $value)
    {
        if (!isset($this->schema[$key])) {
            return sanitize_text_field($value);
        }

        $type = isset($this->schema[$key]['type']) ? $this->schema[$key]['type'] : 'text';

        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'number':
                return floatval($value);
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'multiselect':
                return is_array($value) ? array_map('sanitize_text_field', $value) : [];
            case 'password':
                return $value; // Don't sanitize passwords
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Encrypt sensitive value
     *
     * @param string $value
     * @return string
     */
    private function encrypt($value)
    {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($value);
        }

        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive value
     *
     * @param string $value
     * @return string
     */
    private function decrypt($value)
    {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($value);
        }

        $key = $this->getEncryptionKey();
        $data = base64_decode($value);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key
     *
     * @return string
     */
    private function getEncryptionKey()
    {
        if (defined('AIAGENT_ENCRYPTION_KEY')) {
            return AIAGENT_ENCRYPTION_KEY;
        }
        if (defined('AUTH_KEY')) {
            return AUTH_KEY;
        }
        return 'aiagent-default-key-change-me';
    }

    /**
     * Get section labels
     *
     * @return array
     */
    public function getSectionLabels()
    {
        return $this->sectionLabels;
    }

    /**
     * Check if a setting requires a specific feature
     *
     * @param string $key
     * @return string|null Feature name or null
     */
    public function getRequiredFeature($key)
    {
        if (isset($this->schema[$key]['requires_feature'])) {
            return $this->schema[$key]['requires_feature'];
        }
        return null;
    }

    /**
     * Get settings that require a specific feature
     *
     * @param string $feature
     * @return array
     */
    public function getSettingsRequiringFeature($feature)
    {
        $settings = [];
        foreach ($this->schema as $key => $config) {
            if (isset($config['requires_feature']) && $config['requires_feature'] === $feature) {
                $settings[$key] = $config;
            }
        }
        return $settings;
    }

    /**
     * Import settings from array
     *
     * @param array $settings
     * @return array ['imported' => int, 'errors' => array]
     */
    public function import(array $settings)
    {
        $imported = 0;
        $errors = [];

        foreach ($settings as $key => $value) {
            $validation = $this->validate($key, $value);
            if ($validation['valid']) {
                if ($this->set($key, $value)) {
                    $imported++;
                } else {
                    $errors[] = "Failed to save: {$key}";
                }
            } else {
                $errors[] = "{$key}: {$validation['error']}";
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Export settings to array
     *
     * @param bool $includeSecrets Include encrypted values
     * @return array
     */
    public function export($includeSecrets = false)
    {
        $settings = [];
        
        foreach ($this->schema as $key => $config) {
            // Skip encrypted fields unless explicitly requested
            if (!$includeSecrets && isset($config['encrypted']) && $config['encrypted']) {
                continue;
            }
            $settings[$key] = $this->get($key);
        }
        
        return $settings;
    }

    /**
     * Get setting with feature check
     * Returns default if required feature is not enabled
     *
     * @param string $key
     * @param SubscriptionManager|null $subscription
     * @return mixed
     */
    public function getWithFeatureCheck($key, $subscription = null)
    {
        $requiredFeature = $this->getRequiredFeature($key);
        
        if ($requiredFeature !== null && $subscription !== null) {
            if (!$subscription->isFeatureEnabled($requiredFeature)) {
                return $this->getSchemaDefault($key);
            }
        }
        
        return $this->get($key);
    }

    /**
     * Bulk update settings
     *
     * @param array $settings Key-value pairs
     * @return array ['success' => bool, 'updated' => int, 'errors' => array]
     */
    public function bulkUpdate(array $settings)
    {
        $updated = 0;
        $errors = [];

        foreach ($settings as $key => $value) {
            $validation = $this->validate($key, $value);
            
            if (!$validation['valid']) {
                $errors[$key] = $validation['error'];
                continue;
            }

            if ($this->set($key, $value)) {
                $updated++;
            } else {
                $errors[$key] = 'Failed to save';
            }
        }

        return [
            'success' => empty($errors),
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Get notification email with fallback to admin email
     *
     * @return string
     */
    public function getNotificationEmail()
    {
        $email = $this->get('notify_admin_email');
        
        if (empty($email)) {
            return get_option('admin_email');
        }
        
        return $email;
    }

    /**
     * Check if notifications are enabled for a specific type
     *
     * @param string $type 'high_priority', 'errors', 'daily_summary'
     * @return bool
     */
    public function isNotificationEnabled($type)
    {
        switch ($type) {
            case 'high_priority':
                return (bool) $this->get('notify_on_high_priority', true);
            case 'errors':
                return (bool) $this->get('notify_on_errors', true);
            case 'daily_summary':
                return (bool) $this->get('notify_daily_summary', false);
            default:
                return false;
        }
    }

    /**
     * Get LLM configuration as array
     *
     * @return array
     */
    public function getLLMConfig()
    {
        return [
            'provider' => $this->get('llm_provider', 'ollama'),
            'endpoint' => $this->get('llm_endpoint', 'http://localhost:11434/api/generate'),
            'api_key' => $this->get('llm_api_key', ''),
            'model' => $this->get('llm_model', 'llama2'),
            'temperature' => (float) $this->get('llm_temperature', 0.7),
            'max_tokens' => (int) $this->get('llm_max_tokens', 2000),
            'timeout' => (int) $this->get('llm_timeout', 60),
        ];
    }

    /**
     * Get analysis configuration as array
     *
     * @return array
     */
    public function getAnalysisConfig()
    {
        return [
            'product_limit' => (int) $this->get('analysis_product_limit', 50),
            'customer_limit' => (int) $this->get('analysis_customer_limit', 100),
            'priority_threshold' => (int) $this->get('analysis_priority_threshold', 70),
            'retention_days' => (int) $this->get('analysis_retention_days', 90),
            'enable_sql' => (bool) $this->get('analysis_enable_sql', false),
        ];
    }

    /**
     * Get actions configuration as array
     *
     * @return array
     */
    public function getActionsConfig()
    {
        return [
            'auto_execute' => (bool) $this->get('actions_auto_execute', false),
            'max_per_run' => (int) $this->get('actions_max_per_run', 10),
            'retry_failed' => (bool) $this->get('actions_retry_failed', true),
            'retry_attempts' => (int) $this->get('actions_retry_attempts', 3),
            'enabled_types' => (array) $this->get('actions_enabled_types', ['send_email', 'create_discount']),
            'require_approval' => (array) $this->get('actions_require_approval', ['create_discount', 'update_product']),
        ];
    }

    /**
     * Get schedule configuration as array
     *
     * @return array
     */
    public function getScheduleConfig()
    {
        return [
            'frequency' => $this->get('schedule_frequency', 'daily'),
            'preferred_hours' => (array) $this->get('schedule_preferred_hours', [9, 14]),
            'avoid_hours' => (array) $this->get('schedule_avoid_hours', [0, 1, 2, 3, 4, 5]),
        ];
    }

    /**
     * Check if current hour is suitable for analysis
     *
     * @return bool
     */
    public function isGoodTimeForAnalysis()
    {
        $currentHour = (int) current_time('G');
        $avoidHours = (array) $this->get('schedule_avoid_hours', [0, 1, 2, 3, 4, 5]);
        
        return !in_array($currentHour, $avoidHours);
    }

    /**
     * Clear settings cache
     *
     * @return void
     */
    public function clearCache()
    {
        $this->cache = [];
    }
}
