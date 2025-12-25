<?php
/**
 * Action Executor Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use Forooshyar\Modules\AIAgent\Contracts\ActionInterface;
use Forooshyar\Modules\AIAgent\Actions\SendEmailAction;
use Forooshyar\Modules\AIAgent\Actions\CreateDiscountAction;
use Forooshyar\Modules\AIAgent\Actions\UpdateProductAction;
use Forooshyar\Modules\AIAgent\Actions\ScheduleFollowupAction;
use Forooshyar\Modules\AIAgent\Actions\CreateCampaignAction;
use Forooshyar\Modules\AIAgent\Actions\CreateBundleAction;
use Forooshyar\Modules\AIAgent\Actions\InventoryAlertAction;
use Forooshyar\Modules\AIAgent\Actions\LoyaltyRewardAction;
use Forooshyar\Modules\AIAgent\Actions\SchedulePriceChangeAction;

class ActionExecutor
{
    /** @var array Map of action type => handler class */
    private $handlers = [];

    /** @var SettingsManager */
    private $settings;

    /** @var DatabaseService */
    private $database;

    /** @var Logger */
    private $logger;

    /**
     * @param SettingsManager $settings
     * @param DatabaseService $database
     * @param Logger $logger
     */
    public function __construct(
        SettingsManager $settings,
        DatabaseService $database,
        Logger $logger
    ) {
        $this->settings = $settings;
        $this->database = $database;
        $this->logger = $logger;

        $this->registerDefaultHandlers();
    }

    /**
     * Register default action handlers
     *
     * @return void
     */
    private function registerDefaultHandlers()
    {
        $this->handlers = [
            'send_email' => SendEmailAction::class,
            'create_discount' => CreateDiscountAction::class,
            'update_product' => UpdateProductAction::class,
            'schedule_followup' => ScheduleFollowupAction::class,
            'create_campaign' => CreateCampaignAction::class,
            'create_bundle' => CreateBundleAction::class,
            'inventory_alert' => InventoryAlertAction::class,
            'loyalty_reward' => LoyaltyRewardAction::class,
            'schedule_price_change' => SchedulePriceChangeAction::class,
        ];
    }

    /**
     * Register action handler
     *
     * @param string $type
     * @param string $handlerClass
     * @return void
     */
    public function registerHandler($type, $handlerClass)
    {
        $this->handlers[$type] = $handlerClass;
    }

    /**
     * Execute single action
     *
     * @param string $type
     * @param array $data
     * @return array
     */
    public function execute($type, array $data)
    {
        $this->logger->info('Executing action', ['type' => $type]);

        do_action('aiagent_before_action_execute', $type, $data);

        if (!isset($this->handlers[$type])) {
            return [
                'success' => false,
                'message' => "Unknown action type: {$type}",
                'data' => null,
            ];
        }

        $handlerClass = $this->handlers[$type];
        /** @var ActionInterface $handler */
        $handler = new $handlerClass($this->settings);

        // Check if action is enabled
        if (!$handler->isEnabled()) {
            return [
                'success' => false,
                'message' => "Action type '{$type}' is not enabled",
                'data' => null,
            ];
        }

        // Validate data
        $validation = $handler->validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validation['errors']),
                'data' => null,
            ];
        }

        // Apply filters to action data
        $data = apply_filters('aiagent_action_data', $data, $type);

        try {
            $result = $handler->execute($data);
            do_action('aiagent_after_action_execute', $type, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Action execution failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            do_action('aiagent_action_failed', $type, $e);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Execute action by ID from database
     *
     * @param int $actionId
     * @return array
     */
    public function executeById($actionId)
    {
        $action = $this->database->getAction($actionId);

        if (!$action) {
            return [
                'success' => false,
                'message' => 'Action not found',
                'data' => null,
            ];
        }

        // Check if action can be executed
        if (!in_array($action['status'], ['pending', 'approved'])) {
            return [
                'success' => false,
                'message' => "Action cannot be executed (status: {$action['status']})",
                'data' => null,
            ];
        }

        // Check if requires approval
        if ($action['requires_approval'] && $action['status'] !== 'approved') {
            return [
                'success' => false,
                'message' => 'Action requires approval before execution',
                'data' => null,
            ];
        }

        $result = $this->execute($action['action_type'], $action['action_data']);

        // Update action status
        if ($result['success']) {
            $this->database->updateActionStatus($actionId, 'completed', $result);
        } else {
            $maxRetries = $this->settings->get('actions_retry_attempts', 3);
            $shouldRetry = $this->settings->get('actions_retry_failed', true);

            if ($shouldRetry && $action['retry_count'] < $maxRetries) {
                $this->database->incrementRetry($actionId, $result['message']);
            } else {
                $this->database->updateActionStatus($actionId, 'failed', $result);
            }
        }

        return $result;
    }

    /**
     * Get all available actions
     *
     * @return array
     */
    public function getAvailableActions()
    {
        $actions = [];

        foreach ($this->handlers as $type => $handlerClass) {
            /** @var ActionInterface $handler */
            $handler = new $handlerClass($this->settings);
            $actions[$type] = $handler->getMeta();
        }

        return apply_filters('aiagent_available_actions', $actions);
    }

    /**
     * Get enabled actions based on settings
     *
     * @return array
     */
    public function getEnabledActions()
    {
        $enabledTypes = $this->settings->get('actions_enabled_types', []);
        $available = $this->getAvailableActions();

        return array_filter($available, function ($meta, $type) use ($enabledTypes) {
            return in_array($type, $enabledTypes);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Validate action data
     *
     * @param string $type
     * @param array $data
     * @return array
     */
    public function validateAction($type, array $data)
    {
        if (!isset($this->handlers[$type])) {
            return [
                'valid' => false,
                'errors' => ["Unknown action type: {$type}"],
            ];
        }

        $handlerClass = $this->handlers[$type];
        /** @var ActionInterface $handler */
        $handler = new $handlerClass($this->settings);

        return $handler->validate($data);
    }
}
