<?php
/**
 * AI Agent Controller
 * 
 * Handles all AJAX requests for the AI Agent module
 * 
 * @package Forooshyar\Controllers
 */

namespace Forooshyar\Controllers;

use Forooshyar\WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\AIAgentService;
use Forooshyar\Modules\AIAgent\Services\DatabaseService;
use Forooshyar\Modules\AIAgent\Services\ActionExecutor;
use Forooshyar\Modules\AIAgent\Services\AnalysisJobManager;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;
use Forooshyar\Modules\AIAgent\Database\Migrations;
use function Forooshyar\WPLite\appLogger;

class AIAgentController extends Controller
{
    /**
     * Start async analysis job
     *
     * @return void
     */
    public function startAnalysis(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';

        $db = Container::resolve(DatabaseService::class);
        if (!$db->checkTablesExist()) {
            $migrations = new Migrations();
            $migrations->run();
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $result = $jobManager->startJob($type);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            appLogger("[AIAgent] Start analysis error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel running analysis job
     *
     * @return void
     */
    public function cancelAnalysis(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $result = $jobManager->cancelJob();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            appLogger("[AIAgent] Cancel analysis error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get analysis job progress (lightweight - only reads state)
     *
     * @return void
     */
    public function getAnalysisProgress(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $progress = $jobManager->getJobProgress();
            wp_send_json_success($progress);
        } catch (\Exception $e) {
            appLogger("[AIAgent] Get progress error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Process analysis batch (long-running - handles LLM calls)
     *
     * @return void
     */
    public function processBatch(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        // Increase execution time for this request
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }
        
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $progress = $jobManager->getJobProgress();
            
            if ($progress['is_running'] && !$progress['is_cancelling']) {
                $jobManager->processNextBatch();
                $progress = $jobManager->getJobProgress();
            }
            
            wp_send_json_success($progress);
        } catch (\Exception $e) {
            appLogger("[AIAgent] Process batch error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Acknowledge job completion and clear state
     *
     * @return void
     */
    public function acknowledgeCompletion(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jobManager = Container::resolve(AnalysisJobManager::class);

        try {
            $jobManager->acknowledgeCompletion();
            wp_send_json_success(['message' => __('وضعیت پاک شد', 'forooshyar')]);
        } catch (\Exception $e) {
            appLogger("[AIAgent] Acknowledge completion error: " . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Run analysis (legacy/synchronous)
     *
     * @return void
     */
    public function runAnalysis(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        $db = Container::resolve(DatabaseService::class);
        if (!$db->checkTablesExist()) {
            $migrations = new Migrations();
            $migrations->run();
        }
        
        $service = Container::resolve(AIAgentService::class);

        try {
            $result = $service->runAnalysis($type);
            
            $productsAnalyzed = isset($result['products']['analyzed']) ? $result['products']['analyzed'] : 0;
            $productsTotal = isset($result['products']['total']) ? $result['products']['total'] : 0;
            $productsErrors = isset($result['products']['errors']) ? $result['products']['errors'] : [];
            $customersAnalyzed = isset($result['customers']['analyzed']) ? $result['customers']['analyzed'] : 0;
            $customersTotal = isset($result['customers']['total']) ? $result['customers']['total'] : 0;
            
            if (!empty($productsErrors)) {
                foreach ($productsErrors as $err) {
                    $errMsg = is_array($err) ? (isset($err['error']) ? $err['error'] : json_encode($err)) : $err;
                    $result['errors'][] = sprintf(__('محصول %s: %s', 'forooshyar'), 
                        isset($err['product_id']) ? $err['product_id'] : '?', 
                        $errMsg
                    );
                }
            }
            
            if ($result['success']) {
                if ($productsTotal === 0 && $customersTotal === 0) {
                    $message = __('تحلیل انجام شد اما محصول یا مشتری برای تحلیل یافت نشد.', 'forooshyar');
                } elseif ($productsAnalyzed === 0 && $productsTotal > 0) {
                    $message = sprintf(
                        __('تحلیل انجام شد اما هیچ محصولی با موفقیت تحلیل نشد (%d محصول یافت شد). خطاها را بررسی کنید.', 'forooshyar'),
                        $productsTotal
                    );
                } else {
                    $message = sprintf(
                        __('تحلیل با موفقیت انجام شد. محصولات: %d از %d، مشتریان: %d از %d، اقدامات ایجاد شده: %d', 'forooshyar'),
                        $productsAnalyzed,
                        $productsTotal,
                        $customersAnalyzed,
                        $customersTotal,
                        $result['actions_created']
                    );
                }
                $result['message'] = $message;
            } else {
                $errorMessages = !empty($result['errors']) ? implode(', ', $result['errors']) : __('خطای ناشناخته', 'forooshyar');
                $result['message'] = __('خطا در تحلیل: ', 'forooshyar') . $errorMessages;
            }
            
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Execute an action
     *
     * @return void
     */
    public function executeAction(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $actionId = isset($_POST['action_id']) ? absint($_POST['action_id']) : 0;
        $executor = Container::resolve(ActionExecutor::class);

        try {
            $result = $executor->executeById($actionId);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve an action
     *
     * @return void
     */
    public function approveAction(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $actionId = isset($_POST['action_id']) ? absint($_POST['action_id']) : 0;
        $db = Container::resolve(DatabaseService::class);

        try {
            $db->approveAction($actionId, get_current_user_id());
            do_action('aiagent_action_approved', $actionId, get_current_user_id());
            wp_send_json_success(['message' => __('اقدام تأیید شد', 'forooshyar')]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Dismiss/reject an action
     *
     * @return void
     */
    public function dismissAction(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $actionId = isset($_POST['action_id']) ? absint($_POST['action_id']) : 0;
        $db = Container::resolve(DatabaseService::class);

        try {
            $db->deleteAction($actionId);
            do_action('aiagent_action_dismissed', $actionId, get_current_user_id());
            wp_send_json_success(['message' => __('اقدام حذف شد', 'forooshyar')]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve all pending actions
     *
     * @return void
     */
    public function approveAllActions(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $db = Container::resolve(DatabaseService::class);

        try {
            $approved = $db->approveAllPendingActions(get_current_user_id());
            do_action('aiagent_all_actions_approved', $approved, get_current_user_id());
            wp_send_json_success([
                'message' => sprintf(__('%d اقدام تأیید شد', 'forooshyar'), $approved),
                'approved' => $approved
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Dismiss all pending actions
     *
     * @return void
     */
    public function dismissAllActions(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $db = Container::resolve(DatabaseService::class);

        try {
            $deleted = $db->deleteActionsByStatus(['pending', 'approved']);
            do_action('aiagent_all_actions_dismissed', $deleted, get_current_user_id());
            wp_send_json_success([
                'message' => sprintf(__('%d اقدام حذف شد', 'forooshyar'), $deleted),
                'deleted' => $deleted
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get statistics
     *
     * @return void
     */
    public function getStats(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        $service = Container::resolve(AIAgentService::class);

        try {
            $stats = $service->getStatistics($days);
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Test LLM connection
     *
     * @return void
     */
    public function testConnection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $service = Container::resolve(AIAgentService::class);

        try {
            $result = $service->testConnection();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save settings
     *
     * @return void
     */
    public function saveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $settings = Container::resolve(SettingsManager::class);
        $data = isset($_POST['settings']) ? $_POST['settings'] : [];
        
        if (empty($data)) {
            wp_send_json_error(['message' => __('تنظیماتی ارائه نشده', 'forooshyar')], 400);
        }

        $result = $settings->bulkUpdate($data);

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(__('%d تنظیم با موفقیت ذخیره شد', 'forooshyar'), $result['updated']),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('برخی تنظیمات ذخیره نشدند', 'forooshyar'),
                'errors' => $result['errors'],
            ], 400);
        }
    }

    /**
     * Reset settings
     *
     * @return void
     */
    public function resetSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $settings = Container::resolve(SettingsManager::class);
        $section = isset($_POST['section']) ? sanitize_key($_POST['section']) : null;
        
        if ($section) {
            $settingsBySection = $settings->getBySection();
            if (isset($settingsBySection[$section])) {
                $keys = array_keys($settingsBySection[$section]);
                $settings->reset($keys);
            }
        } else {
            $settings->reset();
        }

        wp_send_json_success([
            'message' => __('تنظیمات به حالت پیش‌فرض بازگردانده شد', 'forooshyar'),
        ]);
    }

    /**
     * Export settings
     *
     * @return void
     */
    public function exportSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $settings = Container::resolve(SettingsManager::class);
        $includeSecrets = isset($_POST['include_secrets']) && $_POST['include_secrets'] === 'true';
        $exportData = $settings->export($includeSecrets);

        wp_send_json_success([
            'settings' => $exportData,
            'exported_at' => current_time('mysql'),
            'version' => SettingsManager::VERSION,
        ]);
    }

    /**
     * Import settings
     *
     * @return void
     */
    public function importSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('دسترسی غیرمجاز', 'forooshyar')], 403);
        }

        $jsonData = isset($_POST['settings_json']) ? wp_unslash($_POST['settings_json']) : '';
        
        if (empty($jsonData)) {
            wp_send_json_error(['message' => __('داده تنظیماتی ارائه نشده', 'forooshyar')], 400);
        }

        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('فرمت JSON نامعتبر', 'forooshyar')], 400);
        }

        $settings = Container::resolve(SettingsManager::class);
        $settingsData = isset($data['settings']) ? $data['settings'] : $data;
        $result = $settings->import($settingsData);

        if (empty($result['errors'])) {
            wp_send_json_success([
                'message' => sprintf(__('%d تنظیم با موفقیت وارد شد', 'forooshyar'), $result['imported']),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('برخی تنظیمات وارد نشدند', 'forooshyar'),
                'imported' => $result['imported'],
                'errors' => $result['errors'],
            ], 400);
        }
    }
}
