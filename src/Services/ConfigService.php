<?php

namespace Forooshyar\Services;

class ConfigService
{
    private const OPTION_PREFIX = 'forooshyar_';
    
    /** @var array */
    private $defaults;
    
    /** @var array */
    private $templateVariables;

    public function __construct()
    {
        $this->loadDefaults();
    }

    /**
     * Get configuration value by key
     */
    public function get(string $key, $default = null)
    {
        $optionKey = self::OPTION_PREFIX . $key;
        $value = get_option($optionKey, $default);
        
        // If no value found and no default provided, try to get from defaults
        if ($value === $default && $default === null && isset($this->defaults[$key])) {
            return $this->defaults[$key];
        }
        
        return $value;
    }

    /**
     * Set configuration value
     */
    public function set(string $key, $value): bool
    {
        $optionKey = self::OPTION_PREFIX . $key;
        
        // Get current value to check if it's actually changing
        $currentValue = get_option($optionKey);
        
        // If value is the same, return true (no error, just no change needed)
        if ($currentValue === $value) {
            return true;
        }
        
        // update_option returns false if value didn't change OR on failure
        // We already checked for no-change above, so false here means actual failure
        $result = update_option($optionKey, $value);
        
        // If update_option returns false but the value now matches, it succeeded
        if (!$result) {
            $newValue = get_option($optionKey);
            return $newValue === $value;
        }
        
        return true;
    }

    /**
     * Get all configuration values
     */
    public function getAll(): array
    {
        $config = [];
        
        // Load all sections from defaults
        foreach ($this->defaults as $section => $sectionDefaults) {
            $config[$section] = $this->get($section, $sectionDefaults);
        }
        
        return $config;
    }

    /**
     * Reset configuration to defaults
     */
    public function reset(): bool
    {
        $success = true;
        
        foreach ($this->defaults as $key => $defaultValue) {
            if (!$this->set($key, $defaultValue)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Validate title template syntax
     */
    public function validateTitleTemplate(string $template): array
    {
        $errors = [];
        $availableVariables = array_keys($this->templateVariables);
        
        // Check for unclosed braces first
        $openBraces = substr_count($template, '{{');
        $closeBraces = substr_count($template, '}}');
        if ($openBraces !== $closeBraces) {
            $errors[] = 'آکولادهای باز و بسته برابر نیستند';
        }
        
        // Check for nested braces
        if (preg_match('/\{\{[^}]*\{\{/', $template) || preg_match('/\}\}[^{]*\}\}/', $template)) {
            $errors[] = 'آکولادهای تو در تو مجاز نیستند';
        }
        
        // Check for valid variable syntax - match all {{...}} patterns
        if (preg_match_all('/\{\{([^}]*)\}\}/', $template, $matches)) {
            foreach ($matches[1] as $variable) {
                $variable = trim($variable);
                
                // Check for empty variables
                if (empty($variable)) {
                    $errors[] = sprintf(
                        'متغیر نامعتبر: متغیر خالی. متغیرهای مجاز: %s',
                        implode(', ', $availableVariables)
                    );
                    continue;
                }
                
                // Check if variable is in allowed list
                if (!in_array($variable, $availableVariables)) {
                    $errors[] = sprintf(
                        'متغیر نامعتبر: %s. متغیرهای مجاز: %s',
                        $variable,
                        implode(', ', $availableVariables)
                    );
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get available template variables
     */
    public function getAvailableVariables(): array
    {
        return $this->templateVariables;
    }

    /**
     * Export configuration
     */
    public function export(): array
    {
        return [
            'config' => $this->getAll(),
            'template_variables' => $this->templateVariables,
            'exported_at' => current_time('mysql'),
            'version' => '2.0.0'
        ];
    }

    /**
     * Import configuration
     */
    public function import(array $config): bool
    {
        if (!isset($config['config']) || !is_array($config['config'])) {
            return false;
        }
        
        $success = true;
        
        foreach ($config['config'] as $key => $value) {
            if (array_key_exists($key, $this->defaults)) {
                if (!$this->set($key, $value)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }

    /**
     * Load default configuration
     */
    private function loadDefaults(): void
    {
        $defaultsConfig = $this->loadConfigFile('defaults');
        $this->defaults = $defaultsConfig['config'] ?? [];
        $this->templateVariables = $defaultsConfig['template_variables'] ?? [];
    }

    /**
     * Load configuration file
     */
    private function loadConfigFile(string $configName): array
    {
        $configPath = plugin_dir_path(__FILE__) . '../../configs/' . $configName . '.php';
        
        if (!file_exists($configPath)) {
            return [];
        }
        
        return require $configPath;
    }
}