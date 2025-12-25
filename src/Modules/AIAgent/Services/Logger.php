<?php
/**
 * Logger Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

class Logger
{
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /** @var array */
    private static $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    /** @var SettingsManager */
    private $settings;

    /** @var string */
    private $logFile;

    /**
     * @param SettingsManager $settings
     */
    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
        $this->logFile = WP_CONTENT_DIR . '/aiagent-debug.log';
    }

    /**
     * Log message with level
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $contextStr = !empty($context) ? ' ' . wp_json_encode($context) : '';

        $logMessage = "[{$timestamp}] [{$levelUpper}] {$message}{$contextStr}" . PHP_EOL;

        // Write to file
        if ($this->settings->get('debug_enabled', false)) {
            error_log($logMessage, 3, $this->logFile);
        }

        // Also log to WordPress debug log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("[AIAgent] {$logMessage}");
        }
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Check if message should be logged based on level
     *
     * @param string $level
     * @return bool
     */
    private function shouldLog($level)
    {
        $configLevel = $this->settings->get('debug_log_level', 'error');
        
        $configLevelNum = isset(self::$levels[$configLevel]) ? self::$levels[$configLevel] : 3;
        $messageLevelNum = isset(self::$levels[$level]) ? self::$levels[$level] : 0;

        return $messageLevelNum >= $configLevelNum;
    }

    /**
     * Log LLM prompt if enabled
     *
     * @param array $messages
     * @param string $provider
     * @return void
     */
    public function logPrompt(array $messages, $provider)
    {
        if (!$this->settings->get('debug_save_prompts', false)) {
            return;
        }

        $this->debug("LLM Prompt [{$provider}]", ['messages' => $messages]);
    }

    /**
     * Log LLM response if enabled
     *
     * @param array $response
     * @param string $provider
     * @return void
     */
    public function logResponse(array $response, $provider)
    {
        if (!$this->settings->get('debug_save_prompts', false)) {
            return;
        }

        $this->debug("LLM Response [{$provider}]", ['response' => $response]);
    }

    /**
     * Clear log file
     *
     * @return bool
     */
    public function clearLog()
    {
        if (file_exists($this->logFile)) {
            return unlink($this->logFile);
        }
        return true;
    }

    /**
     * Get log contents
     *
     * @param int $lines Number of lines to return (0 for all)
     * @return string
     */
    public function getLog($lines = 100)
    {
        if (!file_exists($this->logFile)) {
            return '';
        }

        if ($lines === 0) {
            return file_get_contents($this->logFile);
        }

        $file = new \SplFileObject($this->logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $start = max(0, $totalLines - $lines);
        $output = [];

        $file->seek($start);
        while (!$file->eof()) {
            $output[] = $file->fgets();
        }

        return implode('', $output);
    }
}
