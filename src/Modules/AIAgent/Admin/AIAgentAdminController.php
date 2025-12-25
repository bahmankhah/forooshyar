<?php
/**
 * AI Agent Admin Controller
 * 
 * @package Forooshyar\Modules\AIAgent\Admin
 */

namespace Forooshyar\Modules\AIAgent\Admin;

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
        // Main AI Agent submenu under parent plugin
        add_submenu_page(
            'forooshyar',
            __('AI Sales Agent', 'forooshyar'),
            __('AI Sales Agent', 'forooshyar'),
            'manage_woocommerce',
            'forooshyar-ai-agent',
            [$this, 'dashboardPage']
        );

        // Settings submenu
        add_submenu_page(
            'forooshyar',
            __('AI Agent Settings', 'forooshyar'),
            __('AI Settings', 'forooshyar'),
            'manage_options',
            'forooshyar-ai-settings',
            [$this, 'settingsPage']
        );
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

        include __DIR__ . '/Views/dashboard.php';
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

    /**
     * Render analysis results page
     *
     * @return void
     */
    public function resultsPage()
    {
        include __DIR__ . '/Views/analysis-results.php';
    }

    /**
     * Render actions list page
     *
     * @return void
     */
    public function actionsPage()
    {
        include __DIR__ . '/Views/actions-list.php';
    }
}
