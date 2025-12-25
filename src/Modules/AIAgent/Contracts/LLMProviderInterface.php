<?php
/**
 * LLM Provider Interface
 * 
 * @package Forooshyar\Modules\AIAgent\Contracts
 */

namespace Forooshyar\Modules\AIAgent\Contracts;

interface LLMProviderInterface
{
    /**
     * Send messages to LLM and get response
     *
     * @param array $messages Array of role/content pairs
     * @param array $options Additional options
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     */
    public function call(array $messages, array $options = []);

    /**
     * Test connection to LLM
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection();

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName();

    /**
     * Get available models
     *
     * @return array
     */
    public function getAvailableModels();
}
