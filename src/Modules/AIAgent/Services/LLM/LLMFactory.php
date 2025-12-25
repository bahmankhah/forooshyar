<?php
/**
 * LLM Factory
 * 
 * Creates and manages LLM provider instances.
 * Supports Ollama, OpenAI, and Anthropic providers.
 * 
 * @package Forooshyar\Modules\AIAgent\Services\LLM
 */

namespace Forooshyar\Modules\AIAgent\Services\LLM;

use Forooshyar\Modules\AIAgent\Contracts\LLMProviderInterface;
use Forooshyar\Modules\AIAgent\Exceptions\LLMConnectionException;
use InvalidArgumentException;

class LLMFactory
{
    /** @var array Registered provider classes */
    private static $providers = [
        'ollama' => OllamaProvider::class,
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
    ];

    /** @var array Provider display names */
    private static $providerNames = [
        'ollama' => 'Ollama (Local)',
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic Claude',
    ];

    /** @var array Provider descriptions */
    private static $providerDescriptions = [
        'ollama' => 'Run LLMs locally with Ollama. Free and private.',
        'openai' => 'OpenAI GPT models. Requires API key.',
        'anthropic' => 'Anthropic Claude models. Requires API key.',
    ];

    /** @var array Default configurations per provider */
    private static $defaultConfigs = [
        'ollama' => [
            'endpoint' => 'http://localhost:11434/api/generate',
            'model' => 'llama2',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 60,
        ],
        'openai' => [
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 60,
        ],
        'anthropic' => [
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-sonnet-20240229',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 60,
        ],
    ];

    /** @var array Cached provider instances */
    private static $instances = [];

    /**
     * Create LLM provider based on settings
     *
     * @param string $provider Provider name
     * @param array $config Configuration options
     * @return LLMProviderInterface
     * @throws InvalidArgumentException
     */
    public static function create($provider, array $config = [])
    {
        $provider = strtolower($provider);

        if (!isset(self::$providers[$provider])) {
            throw new InvalidArgumentException(
                sprintf('Unknown LLM provider: %s. Available: %s', $provider, implode(', ', array_keys(self::$providers)))
            );
        }

        // Merge with default config
        $defaultConfig = isset(self::$defaultConfigs[$provider]) ? self::$defaultConfigs[$provider] : [];
        $mergedConfig = array_merge($defaultConfig, $config);

        $class = self::$providers[$provider];
        return new $class($mergedConfig);
    }

    /**
     * Create and cache a provider instance (singleton per provider+config)
     *
     * @param string $provider
     * @param array $config
     * @return LLMProviderInterface
     */
    public static function createCached($provider, array $config = [])
    {
        $cacheKey = $provider . '_' . md5(serialize($config));

        if (!isset(self::$instances[$cacheKey])) {
            self::$instances[$cacheKey] = self::create($provider, $config);
        }

        return self::$instances[$cacheKey];
    }

    /**
     * Clear cached instances
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$instances = [];
    }

    /**
     * Get list of available providers
     *
     * @return array
     */
    public static function getAvailableProviders()
    {
        return array_keys(self::$providers);
    }

    /**
     * Get provider display name
     *
     * @param string $provider
     * @return string
     */
    public static function getProviderName($provider)
    {
        return isset(self::$providerNames[$provider]) ? self::$providerNames[$provider] : ucfirst($provider);
    }

    /**
     * Get provider description
     *
     * @param string $provider
     * @return string
     */
    public static function getProviderDescription($provider)
    {
        return isset(self::$providerDescriptions[$provider]) ? self::$providerDescriptions[$provider] : '';
    }

    /**
     * Get all providers with metadata
     *
     * @return array
     */
    public static function getProvidersWithMetadata()
    {
        $result = [];

        foreach (self::$providers as $key => $class) {
            $result[$key] = [
                'name' => self::getProviderName($key),
                'description' => self::getProviderDescription($key),
                'class' => $class,
                'requires_api_key' => $key !== 'ollama',
                'default_config' => isset(self::$defaultConfigs[$key]) ? self::$defaultConfigs[$key] : [],
            ];
        }

        return $result;
    }

    /**
     * Get default configuration for a provider
     *
     * @param string $provider
     * @return array
     */
    public static function getDefaultConfig($provider)
    {
        return isset(self::$defaultConfigs[$provider]) ? self::$defaultConfigs[$provider] : [];
    }

    /**
     * Register a custom provider
     *
     * @param string $name Provider identifier
     * @param string $class Fully qualified class name
     * @param array $options Optional metadata (name, description, default_config)
     * @return void
     * @throws InvalidArgumentException
     */
    public static function registerProvider($name, $class, array $options = [])
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Provider class does not exist: {$class}");
        }

        if (!in_array(LLMProviderInterface::class, class_implements($class))) {
            throw new InvalidArgumentException("Provider class must implement LLMProviderInterface: {$class}");
        }

        $name = strtolower($name);
        self::$providers[$name] = $class;

        if (isset($options['display_name'])) {
            self::$providerNames[$name] = $options['display_name'];
        }

        if (isset($options['description'])) {
            self::$providerDescriptions[$name] = $options['description'];
        }

        if (isset($options['default_config'])) {
            self::$defaultConfigs[$name] = $options['default_config'];
        }
    }

    /**
     * Unregister a provider
     *
     * @param string $name
     * @return bool
     */
    public static function unregisterProvider($name)
    {
        $name = strtolower($name);

        if (!isset(self::$providers[$name])) {
            return false;
        }

        unset(self::$providers[$name]);
        unset(self::$providerNames[$name]);
        unset(self::$providerDescriptions[$name]);
        unset(self::$defaultConfigs[$name]);

        return true;
    }

    /**
     * Check if provider exists
     *
     * @param string $provider
     * @return bool
     */
    public static function hasProvider($provider)
    {
        return isset(self::$providers[strtolower($provider)]);
    }

    /**
     * Test connection for a provider
     *
     * @param string $provider
     * @param array $config
     * @return array ['success' => bool, 'message' => string, 'provider' => string]
     */
    public static function testProvider($provider, array $config = [])
    {
        try {
            $instance = self::create($provider, $config);
            $result = $instance->testConnection();
            $result['provider'] = $provider;
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'provider' => $provider,
            ];
        }
    }

    /**
     * Get available models for a provider
     *
     * @param string $provider
     * @param array $config
     * @return array
     */
    public static function getModelsForProvider($provider, array $config = [])
    {
        try {
            $instance = self::create($provider, $config);
            return $instance->getAvailableModels();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Validate provider configuration
     *
     * @param string $provider
     * @param array $config
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateConfig($provider, array $config)
    {
        $errors = [];

        if (!self::hasProvider($provider)) {
            $errors[] = "Unknown provider: {$provider}";
            return ['valid' => false, 'errors' => $errors];
        }

        // Check required fields based on provider
        if ($provider !== 'ollama') {
            if (empty($config['api_key'])) {
                $errors[] = 'API key is required for ' . self::getProviderName($provider);
            }
        }

        if (!empty($config['endpoint']) && !filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid endpoint URL';
        }

        if (isset($config['temperature'])) {
            $temp = floatval($config['temperature']);
            if ($temp < 0 || $temp > 2) {
                $errors[] = 'Temperature must be between 0 and 2';
            }
        }

        if (isset($config['max_tokens'])) {
            $tokens = intval($config['max_tokens']);
            if ($tokens < 1 || $tokens > 100000) {
                $errors[] = 'Max tokens must be between 1 and 100000';
            }
        }

        if (isset($config['timeout'])) {
            $timeout = intval($config['timeout']);
            if ($timeout < 1 || $timeout > 600) {
                $errors[] = 'Timeout must be between 1 and 600 seconds';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get recommended model for a use case
     *
     * @param string $provider
     * @param string $useCase 'analysis', 'chat', 'code', 'fast'
     * @return string
     */
    public static function getRecommendedModel($provider, $useCase = 'analysis')
    {
        $recommendations = [
            'ollama' => [
                'analysis' => 'llama2',
                'chat' => 'llama2',
                'code' => 'codellama',
                'fast' => 'llama2',
            ],
            'openai' => [
                'analysis' => 'gpt-4-turbo-preview',
                'chat' => 'gpt-3.5-turbo',
                'code' => 'gpt-4-turbo-preview',
                'fast' => 'gpt-3.5-turbo',
            ],
            'anthropic' => [
                'analysis' => 'claude-3-sonnet-20240229',
                'chat' => 'claude-3-haiku-20240307',
                'code' => 'claude-3-opus-20240229',
                'fast' => 'claude-3-haiku-20240307',
            ],
        ];

        if (isset($recommendations[$provider][$useCase])) {
            return $recommendations[$provider][$useCase];
        }

        // Return default model
        $defaults = self::getDefaultConfig($provider);
        return isset($defaults['model']) ? $defaults['model'] : '';
    }
}
