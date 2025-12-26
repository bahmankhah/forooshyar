<?php
/**
 * AI Agent Admin Controller
 * 
 * @package Forooshyar\Modules\AIAgent\Admin
 */

namespace Forooshyar\Modules\AIAgent\Admin;

use Forooshyar\WPLite\Facades\View;
use Forooshyar\WPLite\Container;
use Forooshyar\Modules\AIAgent\Services\AIAgentService;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;
use Forooshyar\Modules\AIAgent\Services\DatabaseService;

class AIAgentAdminController
{
    /** @var AIAgentService */
    private $service;

    /** @var SubscriptionManager */
    private $subscription;

    /** @var SettingsManager */
    private $settings;

    /**
     * @param AIAgentService $service
     * @param SubscriptionManager $subscription
     * @param SettingsManager $settings
     */
    public function __construct(
        AIAgentService $service,
        SubscriptionManager $subscription,
        SettingsManager $settings
    ) {
        $this->service = $service;
        $this->subscription = $subscription;
        $this->settings = $settings;
    }

    /**
     * Register admin menus
     *
     * @return void
     */
    public function registerMenus()
    {
        // Main AI Agent submenu under parent plugin (Dashboard)
        add_submenu_page(
            'forooshyar',
            __('دستیار فروش هوشمند', 'forooshyar'),
            __('دستیار هوشمند', 'forooshyar'),
            'manage_woocommerce',
            'forooshyar-ai-agent',
            [$this, 'dashboardPage']
        );
        
        // Note: AI Settings menu removed - settings are now in the main settings page as a tab
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function dashboardPage()
    {
        $db = Container::resolve(DatabaseService::class);
        
        // Get current tab and page
        $currentTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        $currentPage = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $perPage = 15;
        
        // Get stats and status
        $stats = $this->service->getStatistics(30);
        $status = $this->service->getStatus();
        
        // Get counts for tabs
        $actionCounts = $db->getActionsCountByStatus();
        $analysisCounts = $db->getAnalysesCountByType();
        
        // Get paginated data based on tab
        $actionsData = null;
        $analysesData = null;
        $statusFilter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $typeFilter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        
        switch ($currentTab) {
            case 'pending':
                $actionsData = $db->getPaginatedActions(['status' => ['pending', 'approved']], $currentPage, $perPage);
                break;
            case 'completed':
                $actionsData = $db->getPaginatedActions(['status' => ['completed', 'failed']], $currentPage, $perPage);
                break;
            case 'actions':
                $filters = [];
                if ($statusFilter) {
                    $filters['status'] = $statusFilter;
                }
                $actionsData = $db->getPaginatedActions($filters, $currentPage, $perPage);
                break;
            case 'analyses':
                $filters = [];
                if ($typeFilter) {
                    $filters['entity_type'] = $typeFilter;
                }
                $analysesData = $db->getPaginatedAnalyses($filters, $currentPage, $perPage);
                break;
            case 'overview':
            default:
                // Get recent items for overview
                $actionsData = [
                    'items' => $db->getRecentActions(5),
                    'total' => $actionCounts['all'],
                ];
                $analysesData = [
                    'items' => $db->getRecentAnalyses(5),
                    'total' => $analysisCounts['all'],
                ];
                break;
        }

        View::render('admin.aiagent-dashboard', [
            'stats' => $stats,
            'status' => $status,
            'currentTab' => $currentTab,
            'currentPage' => $currentPage,
            'perPage' => $perPage,
            'actionCounts' => $actionCounts,
            'analysisCounts' => $analysisCounts,
            'actionsData' => $actionsData,
            'analysesData' => $analysesData,
            'statusFilter' => $statusFilter,
            'typeFilter' => $typeFilter,
        ]);
    }

    /**
     * Render settings page
     * 
     * Note: AI Agent settings are now integrated as a tab in the main settings page.
     * This redirects to the main settings page with the aiagent tab selected.
     *
     * @return void
     */
    public function settingsPage()
    {
        // Redirect to main settings page with aiagent tab
        wp_redirect(admin_url('admin.php?page=forooshyar&tab=aiagent'));
        exit;
    }
}
