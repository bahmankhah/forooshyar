<?php
/**
 * Anthropic (Claude) LLM Provider
 * 
 * @package Forooshyar\Modules\AIAgent\Services\LLM
 */

namespace Forooshyar\Modules\AIAgent\Services\LLM;

use Forooshyar\Modules\AIAgent\Contracts\LLMProviderInterface;

class AnthropicProvider implements LLMProviderInterface
{
    const API_VERSION = '2023-06-01';

    /** @var array */
    private $config;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'api_key' => '',
            'model' => 'claude-3-sonnet-20240229',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 60,
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
                'error' => 'Anthropic API key is not configured',
            ];
        }

        // Extract system message and convert format
        $systemMessage = '';
        $anthropicMessages = [];

        foreach ($messages as $message) {
            $role = isset($message['role']) ? $message['role'] : 'user';
            $content = isset($message['content']) ? $message['content'] : '';

            if ($role === 'system') {
                $systemMessage = $content;
            } else {
                // Anthropic uses 'user' and 'assistant' roles
                $anthropicMessages[] = [
                    'role' => $role === 'assistant' ? 'assistant' : 'user',
                    'content' => $content,
                ];
            }
        }

        $body = [
            'model' => isset($options['model']) ? $options['model'] : $this->config['model'],
            'messages' => $anthropicMessages,
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : $this->config['max_tokens'],
            'temperature' => isset($options['temperature']) ? $options['temperature'] : $this->config['temperature'],
        ];

        if (!empty($systemMessage)) {
            $body['system'] = $systemMessage;
        }

        $startTime = microtime(true);

        $response = wp_remote_post($this->config['endpoint'], [
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->config['api_key'],
                'anthropic-version' => self::API_VERSION,
            ],
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

        // Extract content from response
        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (isset($block['type']) && $block['type'] === 'text') {
                    $content .= isset($block['text']) ? $block['text'] : '';
                }
            }
        }

        // Calculate tokens
        $inputTokens = isset($data['usage']['input_tokens']) ? $data['usage']['input_tokens'] : 0;
        $outputTokens = isset($data['usage']['output_tokens']) ? $data['usage']['output_tokens'] : 0;

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'model' => isset($data['model']) ? $data['model'] : $this->config['model'],
                'tokens' => $inputTokens + $outputTokens,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'duration_ms' => round($duration),
                'stop_reason' => isset($data['stop_reason']) ? $data['stop_reason'] : null,
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
                'message' => 'API key is not configured',
            ];
        }

        // Send a minimal test message
        $result = $this->call([
            ['role' => 'user', 'content' => 'Say "OK" and nothing else.'],
        ], ['max_tokens' => 10]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Successfully connected to Anthropic Claude',
            ];
        }

        return [
            'success' => false,
            'message' => 'Connection failed: ' . ($result['error'] ?: 'Unknown error'),
        ];
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName()
    {
        return 'anthropic';
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function getAvailableModels()
    {
        return [
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307',
            'claude-2.1',
            'claude-2.0',
            'claude-instant-1.2',
        ];
    }

    /**
     * Get model info
     *
     * @param string $model
     * @return array
     */
    public function getModelInfo($model)
    {
        $models = [
            'claude-3-opus-20240229' => [
                'name' => 'Claude 3 Opus',
                'context_window' => 200000,
                'max_output' => 4096,
                'description' => 'Most powerful model for complex tasks',
            ],
            'claude-3-sonnet-20240229' => [
                'name' => 'Claude 3 Sonnet',
                'context_window' => 200000,
                'max_output' => 4096,
                'description' => 'Balanced performance and speed',
            ],
            'claude-3-haiku-20240307' => [
                'name' => 'Claude 3 Haiku',
                'context_window' => 200000,
                'max_output' => 4096,
                'description' => 'Fastest and most compact',
            ],
        ];

        return isset($models[$model]) ? $models[$model] : [];
    }
}
