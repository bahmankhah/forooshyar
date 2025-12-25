<?php
/**
 * Abstract Action Base Class
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

use Forooshyar\Modules\AIAgent\Contracts\ActionInterface;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;

abstract class AbstractAction implements ActionInterface
{
    /** @var string */
    protected $type = '';

    /** @var string */
    protected $name = '';

    /** @var string */
    protected $description = '';

    /** @var array */
    protected $requiredFields = [];

    /** @var array */
    protected $optionalFields = [];

    /** @var SettingsManager */
    protected $settings;

    /**
     * @param SettingsManager $settings
     */
    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Execute the action
     *
     * @param array $data
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    abstract public function execute(array $data);

    /**
     * Validate action data
     *
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $data)
    {
        $errors = [];

        foreach ($this->requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[] = "Missing required field: {$field}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if action requires manual approval
     *
     * @return bool
     */
    public function requiresApproval()
    {
        $requireApproval = $this->settings->get('actions_require_approval', []);
        return in_array($this->type, $requireApproval);
    }

    /**
     * Get action metadata
     *
     * @return array
     */
    public function getMeta()
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'required_fields' => $this->requiredFields,
            'optional_fields' => $this->optionalFields,
            'requires_approval' => $this->requiresApproval(),
        ];
    }

    /**
     * Check if action is enabled in settings
     *
     * @return bool
     */
    public function isEnabled()
    {
        $enabledTypes = $this->settings->get('actions_enabled_types', []);
        return in_array($this->type, $enabledTypes);
    }

    /**
     * Get action type identifier
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get field value with default
     *
     * @param array $data
     * @param string $field
     * @param mixed $default
     * @return mixed
     */
    protected function getField(array $data, $field, $default = null)
    {
        return isset($data[$field]) ? $data[$field] : $default;
    }

    /**
     * Create success response
     *
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function success($message, array $data = [])
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Create error response
     *
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function error($message, array $data = [])
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }
}
