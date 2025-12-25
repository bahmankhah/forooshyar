<?php
/**
 * OpenAI LLM Provider
 * 
 * Full-featured OpenAI integration with support for:
 * - Chat completions
 * - Function calling
 * - JSON mode
 * - Embeddings
 * - Model listing
 * 
 * @package Forooshyar\Modules\AIAgent\Services\LLM
 */

namespace Forooshyar\Modules\AIAgent\Services\LLM;

use Forooshyar\Modules\AIAgent\Contracts\LLMProviderInterface;

class OpenAIProvider implements LLMProviderInterface
{
    const API_BASE = 'https://api.openai.com/v1';

    /** @var array */
    private $config;

    /** @var array Model information cache */
    private static $modelInfo = [
        'gpt-4o' => [
            'name' => 'GPT-4o',
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_functions' => true,
            'supports_json_mode' => true,
            'description' => 'Most capable model, multimodal',
        ],
        'gpt-4-turbo' => [
            'name' => 'GPT-4 Turbo',
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_functions' => true,
            'supports_json_mode' => true,
            'description' => 'Latest GPT-4 with vision capabilities',
        ],
        'gpt-4-turbo-preview' => [
            'name' => 'GPT-4 Turbo Preview',
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_functions' => true,
            'supports_json_mode' => true,
            'description' => 'Preview of latest GPT-4 Turbo',
        ],
        'gpt-4' => [
            'name' => 'GPT-4',
            'context_window' => 8192,
            'max_output' => 4096,
            'supports_functions' => true,
            'supports_json_mode' => false,
            'description' => 'Original GPT-4 model',
        ],
        'gpt-3.5-turbo' => [
            'name' => 'GPT-3.5 Turbo',
            'context_window' => 16385,
            'max_output' => 4096,
            'supports_functions' => true,
            'supports_json_mode' => true,
            'description' => 'Fast and cost-effective',
        ],
        'gpt-3.5-turbo-16k' => [
            'name' => 'GPT-3.5 Turbo 16K',
            'context_window' => 16385,
            'max_output' => 4096,
            'supports_functions' => true,
            'supports_json_mode' => false,
            'description' => 'Extended context GPT-3.5',
        ],
    ];

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'endpoint' => self::API_BASE . '/chat/completions',
            'api_key' => '',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 60,
            'organization' => '',
        ], $config);
    }

    /**
     * Send messages to LLM and get response
     *
     * @param array $messages Array of role/content pairs
     * @param array $options Additional options
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     */
    public function call(array $messages, array $options = [])
    {
        if (empty($this->config['api_key'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'OpenAI API key is not configured',
            ];
        }

        $model = isset($options['model']) ? $options['model'] : $this->config['model'];

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : $this->config['temperature'],
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : $this->config['max_tokens'],
        ];

        // Add optional parameters
        if (isset($options['top_p'])) {
            $body['top_p'] = $options['top_p'];
        }

        if (isset($options['frequency_penalty'])) {
            $body['frequency_penalty'] = $options['frequency_penalty'];
        }

        if (isset($options['presence_penalty'])) {
            $body['presence_penalty'] = $options['presence_penalty'];
        }

        if (isset($options['stop'])) {
            $body['stop'] = $options['stop'];
        }

        // JSON mode
        if (isset($options['json_mode']) && $options['json_mode']) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        // Function calling
        if (isset($options['functions']) && !empty($options['functions'])) {
            $body['tools'] = $this->formatFunctions($options['functions']);
            if (isset($options['function_call'])) {
                $body['tool_choice'] = $options['function_call'];
            }
        }

        // Seed for reproducibility
        if (isset($options['seed'])) {
            $body['seed'] = (int) $options['seed'];
        }

        $startTime = microtime(true);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config['api_key'],
        ];

        if (!empty($this->config['organization'])) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }

        $response = wp_remote_post($this->config['endpoint'], [
            'timeout' => $this->config['timeout'],
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ]);

        $duration = (microtime(true) - $startTime) * 1000;

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        if ($statusCode !== 200) {
            $error = isset($data['error']['message']) ? $data['error']['message'] : "HTTP {$statusCode}";
            return [
                'success' => false,
                'data' => null,
                'error' => $error,
            ];
        }

        return $this->parseResponse($data, $duration);
    }

    /**
     * Parse API response
     *
     * @param array $data
     * @param float $duration
     * @return array
     */
    private function parseResponse(array $data, $duration)
    {
        $choice = isset($data['choices'][0]) ? $data['choices'][0] : [];
        $message = isset($choice['message']) ? $choice['message'] : [];

        $content = isset($message['content']) ? $message['content'] : '';
        $finishReason = isset($choice['finish_reason']) ? $choice['finish_reason'] : null;

        // Handle function calls
        $functionCall = null;
        $toolCalls = null;

        if (isset($message['tool_calls'])) {
            $toolCalls = $message['tool_calls'];
            // Extract first function call for backward compatibility
            if (!empty($toolCalls[0]['function'])) {
                $functionCall = [
                    'name' => $toolCalls[0]['function']['name'],
                    'arguments' => json_decode($toolCalls[0]['function']['arguments'], true),
                ];
            }
        }

        // Token usage
        $usage = isset($data['usage']) ? $data['usage'] : [];
        $promptTokens = isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : 0;
        $completionTokens = isset($usage['completion_tokens']) ? $usage['completion_tokens'] : 0;
        $totalTokens = isset($usage['total_tokens']) ? $usage['total_tokens'] : 0;

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'model' => isset($data['model']) ? $data['model'] : $this->config['model'],
                'tokens' => $totalTokens,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'duration_ms' => round($duration),
                'finish_reason' => $finishReason,
                'function_call' => $functionCall,
                'tool_calls' => $toolCalls,
                'id' => isset($data['id']) ? $data['id'] : null,
                'system_fingerprint' => isset($data['system_fingerprint']) ? $data['system_fingerprint'] : null,
            ],
            'error' => null,
        ];
    }

    /**
     * Format functions for API
     *
     * @param array $functions
     * @return array
     */
    private function formatFunctions(array $functions)
    {
        $tools = [];

        foreach ($functions as $func) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $func['name'],
                    'description' => isset($func['description']) ? $func['description'] : '',
                    'parameters' => isset($func['parameters']) ? $func['parameters'] : ['type' => 'object', 'properties' => []],
                ],
            ];
        }

        return $tools;
    }

    /**
     * Call with function calling support
     *
     * @param array $messages
     * @param array $functions
     * @param array $options
     * @return array
     */
    public function callWithFunctions(array $messages, array $functions, array $options = [])
    {
        $options['functions'] = $functions;
        return $this->call($messages, $options);
    }

    /**
     * Call with JSON mode enabled
     *
     * @param array $messages
     * @param array $options
     * @return array
     */
    public function callJsonMode(array $messages, array $options = [])
    {
        $options['json_mode'] = true;
        $result = $this->call($messages, $options);

        if ($result['success'] && !empty($result['data']['content'])) {
            $decoded = json_decode($result['data']['content'], true);
            if ($decoded !== null) {
                $result['data']['parsed_json'] = $decoded;
            }
        }

        return $result;
    }

    /**
     * Generate embeddings for text
     *
     * @param string|array $input Text or array of texts
     * @param string $model Embedding model
     * @return array
     */
    public function generateEmbeddings($input, $model = 'text-embedding-3-small')
    {
        if (empty($this->config['api_key'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'OpenAI API key is not configured',
            ];
        }

        $body = [
            'model' => $model,
            'input' => $input,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config['api_key'],
        ];

        if (!empty($this->config['organization'])) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }

        $response = wp_remote_post(self::API_BASE . '/embeddings', [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        if ($statusCode !== 200) {
            $error = isset($data['error']['message']) ? $data['error']['message'] : "HTTP {$statusCode}";
            return [
                'success' => false,
                'data' => null,
                'error' => $error,
            ];
        }

        $embeddings = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $item) {
                $embeddings[] = $item['embedding'];
            }
        }

        return [
            'success' => true,
            'data' => [
                'embeddings' => $embeddings,
                'model' => $model,
                'usage' => isset($data['usage']) ? $data['usage'] : [],
            ],
            'error' => null,
        ];
    }

    /**
     * Test connection to LLM
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection()
    {
        if (empty($this->config['api_key'])) {
            return [
                'success' => false,
                'message' => __('کلید API پیکربندی نشده است', 'forooshyar'),
            ];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
        ];

        if (!empty($this->config['organization'])) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }

        $response = wp_remote_get(self::API_BASE . '/models', [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('خطا در اتصال: ', 'forooshyar') . $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode === 401) {
            return [
                'success' => false,
                'message' => __('کلید API نامعتبر است', 'forooshyar'),
            ];
        }

        if ($statusCode === 403) {
            return [
                'success' => false,
                'message' => __('دسترسی رد شد. مجوزهای کلید API را بررسی کنید.', 'forooshyar'),
            ];
        }

        if ($statusCode !== 200) {
            return [
                'success' => false,
                'message' => sprintf(__('خطا در اتصال با کد وضعیت %d', 'forooshyar'), $statusCode),
            ];
        }

        return [
            'success' => true,
            'message' => __('اتصال به OpenAI با موفقیت برقرار شد', 'forooshyar'),
        ];
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName()
    {
        return 'openai';
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function getAvailableModels()
    {
        return array_keys(self::$modelInfo);
    }

    /**
     * Fetch models from API
     *
     * @return array
     */
    public function fetchModelsFromApi()
    {
        if (empty($this->config['api_key'])) {
            return [];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
        ];

        if (!empty($this->config['organization'])) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }

        $response = wp_remote_get(self::API_BASE . '/models', [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data'])) {
            return [];
        }

        $models = [];
        foreach ($data['data'] as $model) {
            // Filter to chat models only
            if (isset($model['id']) && (
                strpos($model['id'], 'gpt-') === 0 ||
                strpos($model['id'], 'o1-') === 0
            )) {
                $models[] = $model['id'];
            }
        }

        sort($models);
        return $models;
    }

    /**
     * Get model info
     *
     * @param string $model
     * @return array
     */
    public function getModelInfo($model)
    {
        return isset(self::$modelInfo[$model]) ? self::$modelInfo[$model] : [];
    }

    /**
     * Check if model supports function calling
     *
     * @param string $model
     * @return bool
     */
    public function supportsFunctions($model = null)
    {
        $model = $model ?: $this->config['model'];
        $info = $this->getModelInfo($model);
        return isset($info['supports_functions']) ? $info['supports_functions'] : false;
    }

    /**
     * Check if model supports JSON mode
     *
     * @param string $model
     * @return bool
     */
    public function supportsJsonMode($model = null)
    {
        $model = $model ?: $this->config['model'];
        $info = $this->getModelInfo($model);
        return isset($info['supports_json_mode']) ? $info['supports_json_mode'] : false;
    }

    /**
     * Estimate token count for text
     *
     * @param string $text
     * @return int
     */
    public function estimateTokens($text)
    {
        // Rough estimation: ~4 characters per token for English
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Get current configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $config = $this->config;
        // Mask API key
        if (!empty($config['api_key'])) {
            $config['api_key'] = substr($config['api_key'], 0, 8) . '...' . substr($config['api_key'], -4);
        }
        return $config;
    }
}
