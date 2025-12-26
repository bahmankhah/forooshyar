<?php
/**
 * AI Agent Admin Controller
 * 
 * @package Forooshyar\Modules\AIAgent\Admin
 */

namespace Forooshyar\Modules\AIAgent\Admin;

use Forooshyar\WPLite\Facades\View;
use Forooshyar\Modules\AIAgent\Services\AIAgentService;
use Forooshyar\Modules\AIAgent\Services\SubscriptionManager;
use Forooshyar\Modules\AIAgent\Services\SettingsManager;

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
        $stats = $this->service->getStatistics(30);
        $status = $this->service->getStatus();
        $dashboardData = $this->service->getDashboardData();

        View::render('admin.aiagent-dashboard', [
            'stats' => $stats,
            'status' => $status,
            'dashboardData' => $dashboardData,
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
