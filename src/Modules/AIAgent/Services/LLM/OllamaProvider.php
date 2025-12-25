<?php
/**
 * Ollama LLM Provider
 * 
 * Provides integration with locally-running Ollama instance.
 * Supports both generate and chat endpoints.
 * 
 * @package Forooshyar\Modules\AIAgent\Services\LLM
 */

namespace Forooshyar\Modules\AIAgent\Services\LLM;

use Forooshyar\Modules\AIAgent\Contracts\LLMProviderInterface;

class OllamaProvider implements LLMProviderInterface
{
    /** @var array */
    private $config;

    /** @var string Base URL without endpoint path */
    private $baseUrl;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'endpoints' => 'http://localhost:11434/api/generate',
            'model' => 'llama2',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 60,
            'use_chat_api' => false,
        ], $config);

        // Extract base URL - handle both with and without trailing /api/generate or /api/chat
        $endpoint = rtrim($this->config['endpoint'], '/');
        if (preg_match('#/api/(generate|chat)$#', $endpoint)) {
            $this->baseUrl = preg_replace('#/api/(generate|chat)$#', '', $endpoint);
        } else {
            // If endpoint doesn't end with /api/generate or /api/chat, use it as base
            $this->baseUrl = $endpoint;
        }
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
        $useChatApi = isset($options['use_chat_api']) ? $options['use_chat_api'] : $this->config['use_chat_api'];

        if ($useChatApi) {
            return $this->callChatApi($messages, $options);
        }

        return $this->callGenerateApi($messages, $options);
    }

    /**
     * Call the generate API endpoint
     *
     * @param array $messages
     * @param array $options
     * @return array
     */
    private function callGenerateApi(array $messages, array $options)
    {
        $prompt = $this->formatMessages($messages);

        $body = [
            'model' => isset($options['model']) ? $options['model'] : $this->config['model'],
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => (float) (isset($options['temperature']) ? $options['temperature'] : $this->config['temperature']),
                'num_predict' => (int) (isset($options['max_tokens']) ? $options['max_tokens'] : $this->config['max_tokens']),
            ],
        ];

        // Add optional parameters
        if (isset($options['top_p'])) {
            $body['options']['top_p'] = $options['top_p'];
        }
        if (isset($options['top_k'])) {
            $body['options']['top_k'] = $options['top_k'];
        }
        if (isset($options['repeat_penalty'])) {
            $body['options']['repeat_penalty'] = $options['repeat_penalty'];
        }

        // Add system prompt if provided separately
        if (isset($options['system'])) {
            $body['system'] = $options['system'];
        }

        $apiUrl = $this->baseUrl . '/api/generate';
        $startTime = microtime(true);

        // Log the prompt being sent
        appLogger("[AIAgent] Ollama Request to: {$apiUrl}");
        appLogger("[AIAgent] Model: {$body['model']}");
        appLogger("[AIAgent] Prompt: " . substr($prompt, 0, 500) . (strlen($prompt) > 500 ? '...' : ''));

        $response = wp_remote_post($apiUrl, [
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'sslverify' => false, // Allow self-signed certificates
        ]);

        $duration = (microtime(true) - $startTime) * 1000;

        // Log the response
        if (is_wp_error($response)) {
            appLogger("[AIAgent] Ollama Error: " . $response->get_error_message());
        } else {
            $statusCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);
            appLogger("[AIAgent] Ollama Response Status: {$statusCode}");
            appLogger("[AIAgent] Ollama Response Body: " . substr($responseBody, 0, 1000) . (strlen($responseBody) > 1000 ? '...' : ''));
            appLogger("[AIAgent] Ollama Duration: {$duration}ms");
        }

        return $this->parseResponse($response, $duration);
    }

    /**
     * Call the chat API endpoint
     *
     * @param array $messages
     * @param array $options
     * @return array
     */
    private function callChatApi(array $messages, array $options)
    {
        $body = [
            'model' => isset($options['model']) ? $options['model'] : $this->config['model'],
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => (float) (isset($options['temperature']) ? $options['temperature'] : $this->config['temperature']),
                'num_predict' => (int) (isset($options['max_tokens']) ? $options['max_tokens'] : $this->config['max_tokens']),
            ],
        ];

        $startTime = microtime(true);

        $response = wp_remote_post($this->baseUrl . '/api/chat', [
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        $duration = (microtime(true) - $startTime) * 1000;

        return $this->parseChatResponse($response, $duration);
    }

    /**
     * Parse generate API response
     *
     * @param mixed $response
     * @param float $duration
     * @return array
     */
    private function parseResponse($response, $duration)
    {
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('خطای اتصال: ', 'forooshyar') . $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        
        // Log raw response for debugging
        if (empty($responseBody)) {
            return [
                'success' => false,
                'data' => null,
                'error' => sprintf(__('پاسخ خالی از سرور (کد وضعیت: %d)', 'forooshyar'), $statusCode),
            ];
        }
        
        $data = json_decode($responseBody, true);

        if ($statusCode !== 200) {
            $errorMsg = isset($data['error']) ? $data['error'] : $responseBody;
            return [
                'success' => false,
                'data' => null,
                'error' => sprintf(__('خطای HTTP %d: %s', 'forooshyar'), $statusCode, $errorMsg),
            ];
        }
        
        if ($data === null) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('پاسخ JSON نامعتبر از سرور', 'forooshyar'),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'content' => isset($data['response']) ? $data['response'] : '',
                'model' => isset($data['model']) ? $data['model'] : $this->config['model'],
                'tokens' => isset($data['eval_count']) ? $data['eval_count'] : 0,
                'prompt_tokens' => isset($data['prompt_eval_count']) ? $data['prompt_eval_count'] : 0,
                'duration_ms' => isset($data['total_duration']) ? round($data['total_duration'] / 1000000) : round($duration),
                'load_duration' => isset($data['load_duration']) ? round($data['load_duration'] / 1000000) : 0,
                'done' => isset($data['done']) ? $data['done'] : true,
                'context' => isset($data['context']) ? $data['context'] : null,
            ],
            'error' => null,
        ];
    }

    /**
     * Parse chat API response
     *
     * @param mixed $response
     * @param float $duration
     * @return array
     */
    private function parseChatResponse($response, $duration)
    {
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
            return [
                'success' => false,
                'data' => null,
                'error' => isset($data['error']) ? $data['error'] : "HTTP {$statusCode}",
            ];
        }

        $content = '';
        if (isset($data['message']['content'])) {
            $content = $data['message']['content'];
        }

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'model' => isset($data['model']) ? $data['model'] : $this->config['model'],
                'tokens' => isset($data['eval_count']) ? $data['eval_count'] : 0,
                'prompt_tokens' => isset($data['prompt_eval_count']) ? $data['prompt_eval_count'] : 0,
                'duration_ms' => isset($data['total_duration']) ? round($data['total_duration'] / 1000000) : round($duration),
                'done' => isset($data['done']) ? $data['done'] : true,
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
        $response = wp_remote_get($this->baseUrl . '/api/tags', [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('اتصال ناموفق: ', 'forooshyar') . $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            return [
                'success' => false,
                'message' => sprintf(__('اتصال ناموفق با کد وضعیت %d', 'forooshyar'), $statusCode),
            ];
        }

        // Check if configured model is available
        $models = $this->getAvailableModels();
        $configuredModel = $this->config['model'];

        if (!empty($models) && !in_array($configuredModel, $models)) {
            return [
                'success' => true,
                'message' => sprintf(
                    __('اتصال به Ollama برقرار شد، اما مدل "%s" نصب نیست. مدل‌های موجود: %s', 'forooshyar'),
                    $configuredModel,
                    implode(', ', array_slice($models, 0, 5))
                ),
            ];
        }

        return [
            'success' => true,
            'message' => __('اتصال به Ollama با موفقیت برقرار شد', 'forooshyar'),
        ];
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName()
    {
        return 'ollama';
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function getAvailableModels()
    {
        $response = wp_remote_get($this->baseUrl . '/api/tags', [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['models'])) {
            return [];
        }

        $models = [];
        foreach ($data['models'] as $model) {
            if (isset($model['name'])) {
                $models[] = $model['name'];
            }
        }

        return $models;
    }

    /**
     * Get detailed model information
     *
     * @param string $modelName
     * @return array|null
     */
    public function getModelInfo($modelName)
    {
        $response = wp_remote_post($this->baseUrl . '/api/show', [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['name' => $modelName]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return null;
        }

        return [
            'name' => $modelName,
            'modelfile' => isset($data['modelfile']) ? $data['modelfile'] : '',
            'parameters' => isset($data['parameters']) ? $data['parameters'] : '',
            'template' => isset($data['template']) ? $data['template'] : '',
            'details' => isset($data['details']) ? $data['details'] : [],
        ];
    }

    /**
     * Pull a model from Ollama library
     *
     * @param string $modelName
     * @return array
     */
    public function pullModel($modelName)
    {
        $response = wp_remote_post($this->baseUrl . '/api/pull', [
            'timeout' => 300, // Model downloads can take a while
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'name' => $modelName,
                'stream' => false,
            ]),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            return [
                'success' => false,
                'message' => "Failed to pull model: HTTP {$statusCode}",
            ];
        }

        return [
            'success' => true,
            'message' => "Model {$modelName} pulled successfully",
        ];
    }

    /**
     * Check if Ollama server is running
     *
     * @return bool
     */
    public function isServerRunning()
    {
        $response = wp_remote_get($this->baseUrl . '/api/tags', [
            'timeout' => 5,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Format messages array into prompt string
     *
     * @param array $messages
     * @return string
     */
    private function formatMessages(array $messages)
    {
        $prompt = '';
        $systemPrompt = '';

        foreach ($messages as $message) {
            $role = isset($message['role']) ? $message['role'] : 'user';
            $content = isset($message['content']) ? $message['content'] : '';

            switch ($role) {
                case 'system':
                    $systemPrompt = $content;
                    break;
                case 'assistant':
                    $prompt .= "Assistant: {$content}\n\n";
                    break;
                case 'user':
                default:
                    $prompt .= "User: {$content}\n\n";
                    break;
            }
        }

        // Prepend system prompt if exists
        if (!empty($systemPrompt)) {
            $prompt = "System: {$systemPrompt}\n\n" . $prompt;
        }

        $prompt .= "Assistant: ";

        return $prompt;
    }

    /**
     * Generate embeddings for text
     *
     * @param string $text
     * @param string|null $model
     * @return array
     */
    public function generateEmbeddings($text, $model = null)
    {
        $response = wp_remote_post($this->baseUrl . '/api/embeddings', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model ?: $this->config['model'],
                'prompt' => $text,
            ]),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['embedding'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'No embedding returned',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'embedding' => $data['embedding'],
                'model' => $model ?: $this->config['model'],
            ],
            'error' => null,
        ];
    }
}
