<?php
/**
 * Context Manager Service
 * 
 * مدیریت پرامپت‌ها و قالب‌های سیستم برای تحلیل‌های هوش مصنوعی
 * این سرویس از جدول aiagent_context برای ذخیره و بازیابی context استفاده می‌کند
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appConfig;
use function Forooshyar\WPLite\appLogger;

class ContextManager
{
    const TYPE_PROMPT = 'prompt';
    const TYPE_TEMPLATE = 'template';
    const TYPE_CONFIG = 'config';

    /** @var string */
    private $table;

    /** @var array Cache for loaded contexts */
    private $cache = [];

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'forooshyar_aiagent_context';
    }

    /**
     * Initialize default contexts from config
     * Should be called on module activation
     *
     * @return int Number of contexts created
     */
    public function initializeDefaults()
    {
        $defaults = appConfig('aiagent.default_contexts', []);
        $created = 0;

        foreach ($defaults as $key => $data) {
            if (!$this->exists($key)) {
                $this->create($key, $data, self::TYPE_PROMPT, true);
                $created++;
            }
        }

        appLogger("[AIAgent] Initialized {$created} default contexts");
        return $created;
    }

    /**
     * Get context by key
     *
     * @param string $key
     * @param mixed $default
     * @return array|null
     */
    public function get($key, $default = null)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE context_key = %s AND is_active = 1",
            $key
        ), ARRAY_A);

        if (!$row) {
            return $default;
        }

        $row['context_data'] = json_decode($row['context_data'], true);
        $this->cache[$key] = $row;

        return $row;
    }

    /**
     * Get context data only (without metadata)
     *
     * @param string $key
     * @param array $default
     * @return array
     */
    public function getData($key, $default = [])
    {
        $context = $this->get($key);
        return $context ? $context['context_data'] : $default;
    }

    /**
     * Check if context exists
     *
     * @param string $key
     * @return bool
     */
    public function exists($key)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE context_key = %s",
            $key
        ));

        return (int) $count > 0;
    }

    /**
     * Create new context
     *
     * @param string $key
     * @param array $data
     * @param string $type
     * @param bool $isDefault
     * @param string|null $description
     * @return int|false Insert ID or false
     */
    public function create($key, array $data, $type = self::TYPE_PROMPT, $isDefault = false, $description = null)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'context_key' => $key,
                'context_type' => $type,
                'context_data' => wp_json_encode($data),
                'description' => $description,
                'is_active' => 1,
                'is_default' => $isDefault ? 1 : 0,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d']
        );

        if ($result) {
            unset($this->cache[$key]);
            appLogger("[AIAgent] Context created: {$key}");
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update existing context
     *
     * @param string $key
     * @param array $data
     * @param string|null $description
     * @return bool
     */
    public function update($key, array $data, $description = null)
    {
        global $wpdb;

        $updateData = [
            'context_data' => wp_json_encode($data),
        ];
        $format = ['%s'];

        if ($description !== null) {
            $updateData['description'] = $description;
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $this->table,
            $updateData,
            ['context_key' => $key],
            $format,
            ['%s']
        );

        if ($result !== false) {
            unset($this->cache[$key]);
            appLogger("[AIAgent] Context updated: {$key}");
        }

        return $result !== false;
    }

    /**
     * Save context (create or update)
     *
     * @param string $key
     * @param array $data
     * @param string $type
     * @param string|null $description
     * @return bool
     */
    public function save($key, array $data, $type = self::TYPE_PROMPT, $description = null)
    {
        if ($this->exists($key)) {
            return $this->update($key, $data, $description);
        }
        return (bool) $this->create($key, $data, $type, false, $description);
    }

    /**
     * Delete context (only non-default)
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        global $wpdb;

        // Don't delete default contexts
        $context = $this->get($key);
        if ($context && $context['is_default']) {
            appLogger("[AIAgent] Cannot delete default context: {$key}");
            return false;
        }

        $result = $wpdb->delete(
            $this->table,
            ['context_key' => $key],
            ['%s']
        );

        if ($result) {
            unset($this->cache[$key]);
            appLogger("[AIAgent] Context deleted: {$key}");
        }

        return (bool) $result;
    }

    /**
     * Activate/deactivate context
     *
     * @param string $key
     * @param bool $active
     * @return bool
     */
    public function setActive($key, $active)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            ['is_active' => $active ? 1 : 0],
            ['context_key' => $key],
            ['%d'],
            ['%s']
        );

        if ($result !== false) {
            unset($this->cache[$key]);
        }

        return $result !== false;
    }

    /**
     * Get all contexts by type
     *
     * @param string|null $type
     * @param bool $activeOnly
     * @return array
     */
    public function getAll($type = null, $activeOnly = true)
    {
        global $wpdb;

        $where = [];
        $params = [];

        if ($type !== null) {
            $where[] = 'context_type = %s';
            $params[] = $type;
        }

        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY context_key ASC";

        $rows = !empty($params)
            ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        foreach ($rows as &$row) {
            $row['context_data'] = json_decode($row['context_data'], true);
        }

        return $rows;
    }

    /**
     * Get all prompts
     *
     * @return array
     */
    public function getPrompts()
    {
        return $this->getAll(self::TYPE_PROMPT);
    }

    /**
     * Get all templates
     *
     * @return array
     */
    public function getTemplates()
    {
        return $this->getAll(self::TYPE_TEMPLATE);
    }

    /**
     * Build system prompt for analysis
     *
     * @param string $analysisType 'product_analysis' or 'customer_analysis'
     * @return string
     */
    public function buildSystemPrompt($analysisType)
    {
        // Get base system prompt
        $systemPrompt = $this->getData('system_prompt', []);
        
        // Get analysis-specific prompt
        $analysisPrompt = $this->getData($analysisType . '_prompt', []);

        // Merge and format
        $parts = [];

        if (!empty($systemPrompt['role'])) {
            $parts[] = $systemPrompt['role'];
        }

        if (!empty($systemPrompt['objective'])) {
            $parts[] = $systemPrompt['objective'];
        }

        if (!empty($analysisPrompt['instructions'])) {
            $parts[] = $analysisPrompt['instructions'];
        }

        if (!empty($systemPrompt['response_format'])) {
            $parts[] = "\n" . implode("\n", $systemPrompt['response_format']);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Get email template
     *
     * @param string $templateKey
     * @param array $variables
     * @return string|null
     */
    public function getEmailTemplate($templateKey, array $variables = [])
    {
        $template = $this->getData('email_template_' . $templateKey);
        
        if (!$template || empty($template['content'])) {
            return null;
        }

        $content = $template['content'];

        // Replace variables
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Get SMS template
     *
     * @param string $templateKey
     * @param array $variables
     * @return string|null
     */
    public function getSmsTemplate($templateKey, array $variables = [])
    {
        $template = $this->getData('sms_template_' . $templateKey);
        
        if (!$template || empty($template['content'])) {
            return null;
        }

        $content = $template['content'];

        // Replace variables
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Reset context to default
     *
     * @param string $key
     * @return bool
     */
    public function resetToDefault($key)
    {
        $defaults = appConfig('aiagent.default_contexts', []);
        
        if (!isset($defaults[$key])) {
            return false;
        }

        return $this->update($key, $defaults[$key]);
    }

    /**
     * Reset all contexts to defaults
     *
     * @return int Number of contexts reset
     */
    public function resetAllToDefaults()
    {
        $defaults = appConfig('aiagent.default_contexts', []);
        $reset = 0;

        foreach ($defaults as $key => $data) {
            if ($this->exists($key)) {
                $this->update($key, $data);
            } else {
                $this->create($key, $data, self::TYPE_PROMPT, true);
            }
            $reset++;
        }

        $this->cache = [];
        appLogger("[AIAgent] Reset {$reset} contexts to defaults");

        return $reset;
    }

    /**
     * Export all contexts
     *
     * @return array
     */
    public function export()
    {
        $contexts = $this->getAll(null, false);
        $export = [];

        foreach ($contexts as $context) {
            $export[$context['context_key']] = [
                'type' => $context['context_type'],
                'data' => $context['context_data'],
                'description' => $context['description'],
                'is_default' => (bool) $context['is_default'],
            ];
        }

        return $export;
    }

    /**
     * Import contexts
     *
     * @param array $data
     * @param bool $overwrite
     * @return array ['imported' => int, 'skipped' => int, 'errors' => array]
     */
    public function import(array $data, $overwrite = false)
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data as $key => $context) {
            if (!isset($context['data']) || !is_array($context['data'])) {
                $errors[] = "Invalid data for context: {$key}";
                continue;
            }

            $exists = $this->exists($key);

            if ($exists && !$overwrite) {
                $skipped++;
                continue;
            }

            $type = isset($context['type']) ? $context['type'] : self::TYPE_PROMPT;
            $description = isset($context['description']) ? $context['description'] : null;

            if ($exists) {
                $this->update($key, $context['data'], $description);
            } else {
                $this->create($key, $context['data'], $type, false, $description);
            }

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Clear cache
     *
     * @return void
     */
    public function clearCache()
    {
        $this->cache = [];
    }
}
