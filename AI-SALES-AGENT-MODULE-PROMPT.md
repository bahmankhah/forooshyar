# AI Sales Agent Module - Development Specification

## Project Context

You are developing a **modular AI Sales Agent** feature for an existing WPLite-based WordPress plugin. The parent plugin's core purpose is creating APIs for third-party marketplace services (like Torob, Emalls) to access WooCommerce product data.

This AI Sales Agent module must be:
- **Completely separate** from the core marketplace API functionality
- **Subscription-gated** (activatable via configuration, no payment processing yet)
- **Seamlessly integrated** into the existing plugin's admin dashboard
- **PHP 7.4+ compatible** (no union types, no named arguments, no match expressions)
- **Scalable and clean** following SOLID principles

---

## Technical Requirements

### PHP Version Compatibility (CRITICAL)
```php
// ❌ NOT ALLOWED (PHP 8.0+)
public function process(string|array $data): mixed {}
function test(name: 'value') {}
match($x) { 1 => 'one' };

// ✅ ALLOWED (PHP 7.4)
public function process($data) {}
/** @param string|array $data */
/** @return mixed */
```

### Framework: WPLite
- Uses Laravel-style dependency injection container
- Service providers for bootstrapping
- Container bindings: `\WPLite\Container::bind()` and `\WPLite\Container::resolve()`
- Facades available: `\WPLite\Facades\App`


---

## Module Architecture

### Directory Structure
```
src/
├── Modules/
│   └── AIAgent/
│       ├── AIAgentModule.php              # Module bootstrap & registration
│       ├── Config/
│       │   └── ai-agent.php               # All module configuration
│       ├── Contracts/
│       │   ├── LLMProviderInterface.php
│       │   ├── ActionInterface.php
│       │   ├── AnalyzerInterface.php
│       │   └── SubscriptionInterface.php
│       ├── Services/
│       │   ├── SubscriptionManager.php    # Controls feature access
│       │   ├── SettingsManager.php        # Handles all settings
│       │   ├── AIAgentService.php         # Main orchestrator
│       │   ├── ProductAnalyzer.php
│       │   ├── CustomerAnalyzer.php
│       │   ├── ActionExecutor.php
│       │   ├── DatabaseService.php
│       │   ├── CacheService.php
│       │   ├── RateLimitService.php
│       │   └── LLM/
│       │       ├── OllamaProvider.php
│       │       ├── OpenAIProvider.php
│       │       └── LLMFactory.php
│       ├── Actions/                       # Individual action handlers
│       │   ├── AbstractAction.php
│       │   ├── SendEmailAction.php
│       │   ├── CreateDiscountAction.php
│       │   ├── UpdateProductAction.php
│       │   ├── ScheduleFollowupAction.php
│       │   ├── CreateCampaignAction.php
│       │   ├── CreateBundleAction.php
│       │   ├── InventoryAlertAction.php
│       │   ├── LoyaltyRewardAction.php
│       │   └── SchedulePriceChangeAction.php
│       ├── Admin/
│       │   ├── AIAgentAdminController.php
│       │   ├── SettingsController.php
│       │   └── Views/
│       │       ├── dashboard.php
│       │       ├── settings.php
│       │       ├── analysis-results.php
│       │       ├── actions-list.php
│       │       └── partials/
│       │           ├── stats-cards.php
│       │           ├── action-table.php
│       │           └── subscription-notice.php
│       ├── Commands/
│       │   └── AIAgentCommand.php         # WP-CLI commands
│       ├── Database/
│       │   ├── Migrations.php
│       │   └── Schema.php
│       └── Events/
│           ├── AnalysisCompleted.php
│           └── ActionExecuted.php
```


---

## Subscription & Feature Gating System

### SubscriptionManager Requirements

```php
<?php
namespace App\Modules\AIAgent\Services;

class SubscriptionManager
{
    const FEATURE_PRODUCT_ANALYSIS = 'product_analysis';
    const FEATURE_CUSTOMER_ANALYSIS = 'customer_analysis';
    const FEATURE_AUTO_ACTIONS = 'auto_actions';
    const FEATURE_SQL_ANALYSIS = 'sql_analysis';
    const FEATURE_ADVANCED_REPORTS = 'advanced_reports';
    const FEATURE_MULTI_LLM = 'multi_llm_providers';
    
    /**
     * Check if AI Agent module is globally enabled
     * @return bool
     */
    public function isModuleEnabled();
    
    /**
     * Check if specific feature is enabled
     * @param string $feature
     * @return bool
     */
    public function isFeatureEnabled($feature);
    
    /**
     * Get subscription tier (free, basic, pro, enterprise)
     * @return string
     */
    public function getSubscriptionTier();
    
    /**
     * Get all enabled features for current tier
     * @return array
     */
    public function getEnabledFeatures();
    
    /**
     * Check usage limits (analyses per day, actions per day)
     * @param string $limitType
     * @return array ['allowed' => int, 'used' => int, 'remaining' => int]
     */
    public function checkUsageLimit($limitType);
}
```

### Subscription Tiers Configuration
```php
// Config/ai-agent.php
return [
    'subscription' => [
        'enabled' => get_option('aiagent_module_enabled', false),
        'tier' => get_option('aiagent_subscription_tier', 'free'),
        
        'tiers' => [
            'free' => [
                'features' => ['product_analysis'],
                'limits' => [
                    'analyses_per_day' => 5,
                    'actions_per_day' => 0,
                    'products_per_analysis' => 10,
                    'customers_per_analysis' => 0,
                ],
                'llm_providers' => ['ollama'],
            ],
            'basic' => [
                'features' => ['product_analysis', 'customer_analysis', 'auto_actions'],
                'limits' => [
                    'analyses_per_day' => 20,
                    'actions_per_day' => 50,
                    'products_per_analysis' => 50,
                    'customers_per_analysis' => 100,
                ],
                'llm_providers' => ['ollama', 'openai'],
            ],
            'pro' => [
                'features' => ['product_analysis', 'customer_analysis', 'auto_actions', 'sql_analysis', 'advanced_reports'],
                'limits' => [
                    'analyses_per_day' => 100,
                    'actions_per_day' => 500,
                    'products_per_analysis' => 200,
                    'customers_per_analysis' => 500,
                ],
                'llm_providers' => ['ollama', 'openai', 'anthropic'],
            ],
            'enterprise' => [
                'features' => ['*'], // All features
                'limits' => [
                    'analyses_per_day' => -1, // Unlimited
                    'actions_per_day' => -1,
                    'products_per_analysis' => -1,
                    'customers_per_analysis' => -1,
                ],
                'llm_providers' => ['*'],
            ],
        ],
    ],
];
```


---

## Settings System (Fully Configurable)

### SettingsManager Requirements

All settings must be:
1. Stored in WordPress options table with prefix `aiagent_`
2. Accessible via admin UI
3. Validated before saving
4. Have sensible defaults

```php
<?php
namespace App\Modules\AIAgent\Services;

class SettingsManager
{
    const SETTINGS_GROUP = 'aiagent_settings';
    
    /**
     * Get setting value with default fallback
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null);
    
    /**
     * Set setting value with validation
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set($key, $value);
    
    /**
     * Get all settings as array
     * @return array
     */
    public function all();
    
    /**
     * Reset settings to defaults
     * @param array|null $keys Specific keys or null for all
     * @return void
     */
    public function reset($keys = null);
    
    /**
     * Validate setting value
     * @param string $key
     * @param mixed $value
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validate($key, $value);
}
```

### Complete Settings Schema
```php
// All configurable settings with types and defaults
$settings_schema = [
    // === MODULE SETTINGS ===
    'module_enabled' => [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Enable AI Sales Agent Module',
        'section' => 'general',
    ],
    
    // === LLM PROVIDER SETTINGS ===
    'llm_provider' => [
        'type' => 'select',
        'default' => 'ollama',
        'options' => ['ollama', 'openai', 'anthropic'],
        'label' => 'LLM Provider',
        'section' => 'llm',
    ],
    'llm_endpoint' => [
        'type' => 'url',
        'default' => 'http://localhost:11434/api/generate',
        'label' => 'LLM API Endpoint',
        'section' => 'llm',
    ],
    'llm_api_key' => [
        'type' => 'password',
        'default' => '',
        'label' => 'LLM API Key',
        'section' => 'llm',
        'encrypted' => true,
    ],
    'llm_model' => [
        'type' => 'text',
        'default' => 'llama2',
        'label' => 'LLM Model Name',
        'section' => 'llm',
    ],
    'llm_temperature' => [
        'type' => 'number',
        'default' => 0.7,
        'min' => 0,
        'max' => 2,
        'step' => 0.1,
        'label' => 'LLM Temperature',
        'section' => 'llm',
    ],
    'llm_max_tokens' => [
        'type' => 'number',
        'default' => 2000,
        'min' => 100,
        'max' => 8000,
        'label' => 'Max Tokens',
        'section' => 'llm',
    ],
    'llm_timeout' => [
        'type' => 'number',
        'default' => 60,
        'min' => 10,
        'max' => 300,
        'label' => 'Request Timeout (seconds)',
        'section' => 'llm',
    ],
    
    // === ANALYSIS SETTINGS ===
    'analysis_product_limit' => [
        'type' => 'number',
        'default' => 50,
        'min' => 1,
        'max' => 500,
        'label' => 'Products per Analysis',
        'section' => 'analysis',
    ],
    'analysis_customer_limit' => [
        'type' => 'number',
        'default' => 100,
        'min' => 1,
        'max' => 1000,
        'label' => 'Customers per Analysis',
        'section' => 'analysis',
    ],
    'analysis_priority_threshold' => [
        'type' => 'number',
        'default' => 70,
        'min' => 1,
        'max' => 100,
        'label' => 'Auto-Execute Priority Threshold',
        'section' => 'analysis',
    ],
    'analysis_retention_days' => [
        'type' => 'number',
        'default' => 90,
        'min' => 7,
        'max' => 365,
        'label' => 'Analysis Data Retention (days)',
        'section' => 'analysis',
    ],
    'analysis_enable_sql' => [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Enable SQL-Based Analysis',
        'section' => 'analysis',
        'requires_feature' => 'sql_analysis',
    ],
    
    // === ACTION SETTINGS ===
    'actions_auto_execute' => [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Auto-Execute High Priority Actions',
        'section' => 'actions',
    ],
    'actions_max_per_run' => [
        'type' => 'number',
        'default' => 10,
        'min' => 1,
        'max' => 100,
        'label' => 'Max Actions per Run',
        'section' => 'actions',
    ],
    'actions_retry_failed' => [
        'type' => 'boolean',
        'default' => true,
        'label' => 'Retry Failed Actions',
        'section' => 'actions',
    ],
    'actions_retry_attempts' => [
        'type' => 'number',
        'default' => 3,
        'min' => 1,
        'max' => 10,
        'label' => 'Retry Attempts',
        'section' => 'actions',
    ],
    'actions_enabled_types' => [
        'type' => 'multiselect',
        'default' => ['send_email', 'create_discount'],
        'options' => [
            'send_email', 'send_sms', 'create_discount', 'update_product',
            'create_campaign', 'schedule_followup', 'create_bundle',
            'inventory_alert', 'loyalty_reward', 'schedule_price_change'
        ],
        'label' => 'Enabled Action Types',
        'section' => 'actions',
    ],
    'actions_require_approval' => [
        'type' => 'multiselect',
        'default' => ['create_discount', 'update_product'],
        'options' => ['create_discount', 'update_product', 'schedule_price_change'],
        'label' => 'Actions Requiring Manual Approval',
        'section' => 'actions',
    ],
    
    // === SCHEDULE SETTINGS ===
    'schedule_frequency' => [
        'type' => 'select',
        'default' => 'daily',
        'options' => ['hourly', 'twice_daily', 'daily', 'weekly', 'manual'],
        'label' => 'Analysis Frequency',
        'section' => 'schedule',
    ],
    'schedule_preferred_hours' => [
        'type' => 'multiselect',
        'default' => [9, 14],
        'options' => range(0, 23),
        'label' => 'Preferred Analysis Hours',
        'section' => 'schedule',
    ],
    'schedule_avoid_hours' => [
        'type' => 'multiselect',
        'default' => [0, 1, 2, 3, 4, 5],
        'options' => range(0, 23),
        'label' => 'Hours to Avoid',
        'section' => 'schedule',
    ],
    
    // === NOTIFICATION SETTINGS ===
    'notify_admin_email' => [
        'type' => 'email',
        'default' => '', // Falls back to admin_email
        'label' => 'Notification Email',
        'section' => 'notifications',
    ],
    'notify_on_high_priority' => [
        'type' => 'boolean',
        'default' => true,
        'label' => 'Notify on High Priority Actions',
        'section' => 'notifications',
    ],
    'notify_on_errors' => [
        'type' => 'boolean',
        'default' => true,
        'label' => 'Notify on Errors',
        'section' => 'notifications',
    ],
    'notify_daily_summary' => [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Send Daily Summary',
        'section' => 'notifications',
    ],
    
    // === RATE LIMITING ===
    'rate_limit_per_hour' => [
        'type' => 'number',
        'default' => 100,
        'min' => 10,
        'max' => 1000,
        'label' => 'API Calls per Hour',
        'section' => 'rate_limiting',
    ],
    'rate_limit_per_day' => [
        'type' => 'number',
        'default' => 1000,
        'min' => 100,
        'max' => 10000,
        'label' => 'API Calls per Day',
        'section' => 'rate_limiting',
    ],
    
    // === DEBUG SETTINGS ===
    'debug_enabled' => [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Enable Debug Mode',
        'section' => 'debug',
    ],
    'debug_log_level' => [
        'type' => 'select',
        'default' => 'error',
        'options' => ['debug', 'info', 'warning', 'error'],
        'label' => 'Log Level',
        'section' => 'debug',
    ],
    'debug_save_prompts' => [
        'type' => 'boolean',
        'default' => false,
        'label' => 'Save LLM Prompts to Log',
        'section' => 'debug',
    ],
];
```


---

## Core Services Implementation

### AIAgentModule (Module Bootstrap)

```php
<?php
namespace App\Modules\AIAgent;

class AIAgentModule
{
    /** @var bool */
    private $booted = false;
    
    /**
     * Register module with container
     * Called during plugin registration phase
     */
    public function register();
    
    /**
     * Boot module services
     * Called during plugin boot phase
     */
    public function boot();
    
    /**
     * Check if module should be active
     * @return bool
     */
    public function shouldActivate();
    
    /**
     * Run database migrations
     */
    public function migrate();
    
    /**
     * Register admin menus and pages
     */
    public function registerAdminPages();
    
    /**
     * Register WP-CLI commands
     */
    public function registerCommands();
    
    /**
     * Register WordPress hooks
     */
    public function registerHooks();
}
```

### AIAgentService (Main Orchestrator)

```php
<?php
namespace App\Modules\AIAgent\Services;

class AIAgentService
{
    /** @var SubscriptionManager */
    private $subscription;
    
    /** @var SettingsManager */
    private $settings;
    
    /** @var ProductAnalyzer */
    private $productAnalyzer;
    
    /** @var CustomerAnalyzer */
    private $customerAnalyzer;
    
    /** @var ActionExecutor */
    private $actionExecutor;
    
    /**
     * Run complete analysis cycle
     * @return array Results with counts and errors
     */
    public function runAnalysis();
    
    /**
     * Run product analysis only
     * @return array
     */
    public function analyzeProducts();
    
    /**
     * Run customer analysis only
     * @return array
     */
    public function analyzeCustomers();
    
    /**
     * Execute pending actions
     * @param int|null $limit
     * @return array
     */
    public function executeActions($limit = null);
    
    /**
     * Get analysis statistics
     * @param int $days
     * @return array
     */
    public function getStatistics($days = 30);
    
    /**
     * Test LLM connection
     * @return array
     */
    public function testConnection();
}
```

### LLM Provider Interface & Factory

```php
<?php
namespace App\Modules\AIAgent\Contracts;

interface LLMProviderInterface
{
    /**
     * Send messages to LLM and get response
     * @param array $messages Array of role/content pairs
     * @param array $options Additional options
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     */
    public function call(array $messages, array $options = []);
    
    /**
     * Test connection to LLM
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection();
    
    /**
     * Get provider name
     * @return string
     */
    public function getProviderName();
    
    /**
     * Get available models
     * @return array
     */
    public function getAvailableModels();
}

// Factory for creating providers
namespace App\Modules\AIAgent\Services\LLM;

class LLMFactory
{
    /**
     * Create LLM provider based on settings
     * @param string $provider
     * @param array $config
     * @return LLMProviderInterface
     * @throws \InvalidArgumentException
     */
    public static function create($provider, array $config);
    
    /**
     * Get list of available providers
     * @return array
     */
    public static function getAvailableProviders();
}
```


---

## Action System

### AbstractAction Base Class

```php
<?php
namespace App\Modules\AIAgent\Actions;

abstract class AbstractAction
{
    /** @var string */
    protected $type;
    
    /** @var string */
    protected $name;
    
    /** @var string */
    protected $description;
    
    /** @var array */
    protected $requiredFields = [];
    
    /** @var array */
    protected $optionalFields = [];
    
    /**
     * Execute the action
     * @param array $data
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    abstract public function execute(array $data);
    
    /**
     * Validate action data
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $data);
    
    /**
     * Check if action requires manual approval
     * @return bool
     */
    public function requiresApproval();
    
    /**
     * Get action metadata
     * @return array
     */
    public function getMeta();
    
    /**
     * Check if action is enabled in settings
     * @return bool
     */
    public function isEnabled();
}
```

### ActionExecutor Service

```php
<?php
namespace App\Modules\AIAgent\Services;

class ActionExecutor
{
    /** @var array Map of action type => handler class */
    private $handlers = [];
    
    /**
     * Register action handler
     * @param string $type
     * @param string $handlerClass
     */
    public function registerHandler($type, $handlerClass);
    
    /**
     * Execute single action
     * @param string $type
     * @param array $data
     * @return array
     */
    public function execute($type, array $data);
    
    /**
     * Execute action by ID from database
     * @param int $actionId
     * @return array
     */
    public function executeById($actionId);
    
    /**
     * Get all available actions
     * @return array
     */
    public function getAvailableActions();
    
    /**
     * Get enabled actions based on settings
     * @return array
     */
    public function getEnabledActions();
    
    /**
     * Validate action data
     * @param string $type
     * @param array $data
     * @return array
     */
    public function validateAction($type, array $data);
}
```

### Required Actions to Implement

| Action Type | Required Fields | Optional Fields | Description |
|-------------|-----------------|-----------------|-------------|
| `send_email` | email, subject, message | customer_id, template | Send personalized email |
| `send_sms` | phone, message | customer_id | Send SMS notification |
| `create_discount` | code, amount | type, expiry_date, customer_id, product_ids | Create WooCommerce coupon |
| `update_product` | product_id | price, sale_price, stock_quantity, featured | Update product data |
| `create_campaign` | campaign_name, target_audience | message, channels, budget | Create marketing campaign |
| `schedule_followup` | schedule_time, action | customer_id, product_id, message | Schedule future action |
| `create_bundle` | product_ids, bundle_name, discount_amount | description, expiry_date | Create product bundle |
| `inventory_alert` | product_id, threshold | alert_email | Set low stock alert |
| `loyalty_reward` | customer_id, reward_type, amount | reason, expiry_date | Issue loyalty reward |
| `schedule_price_change` | product_id, new_price, schedule_time | revert_time, reason | Schedule price change |


---

## Database Schema

### Required Tables

```sql
-- Analysis results table
CREATE TABLE {prefix}aiagent_analysis (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    analysis_type VARCHAR(50) NOT NULL,          -- 'product_analysis', 'customer_analysis'
    entity_id BIGINT(20) UNSIGNED NOT NULL,      -- Product ID or Customer ID
    entity_type VARCHAR(20) NOT NULL,            -- 'product', 'customer'
    analysis_data LONGTEXT NOT NULL,             -- JSON: full analysis result
    suggestions LONGTEXT NOT NULL,               -- JSON: array of suggested actions
    priority_score TINYINT(3) UNSIGNED DEFAULT 0,-- 1-100 priority score
    status VARCHAR(20) DEFAULT 'completed',      -- 'pending', 'completed', 'failed'
    llm_provider VARCHAR(50) DEFAULT NULL,       -- Provider used for analysis
    llm_model VARCHAR(100) DEFAULT NULL,         -- Model used
    tokens_used INT(11) DEFAULT 0,               -- Token count for billing
    duration_ms INT(11) DEFAULT 0,               -- Processing time
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_type_status (analysis_type, status),
    KEY idx_priority (priority_score),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Actions table
CREATE TABLE {prefix}aiagent_actions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    analysis_id BIGINT(20) UNSIGNED DEFAULT NULL,-- Link to analysis (nullable for manual)
    action_type VARCHAR(50) NOT NULL,            -- Action type identifier
    action_data LONGTEXT NOT NULL,               -- JSON: action parameters
    status VARCHAR(20) DEFAULT 'pending',        -- 'pending', 'approved', 'completed', 'failed', 'cancelled'
    priority_score TINYINT(3) UNSIGNED DEFAULT 50,
    requires_approval TINYINT(1) DEFAULT 0,
    approved_by BIGINT(20) UNSIGNED DEFAULT NULL,-- User ID who approved
    approved_at DATETIME DEFAULT NULL,
    executed_at DATETIME DEFAULT NULL,
    result LONGTEXT DEFAULT NULL,                -- JSON: execution result
    error_message TEXT DEFAULT NULL,
    retry_count TINYINT(3) UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_analysis (analysis_id),
    KEY idx_type_status (action_type, status),
    KEY idx_priority (priority_score DESC),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Context/Prompts table
CREATE TABLE {prefix}aiagent_context (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    context_key VARCHAR(100) NOT NULL,           -- Unique identifier
    context_type VARCHAR(50) DEFAULT 'prompt',   -- 'prompt', 'template', 'config'
    context_data LONGTEXT NOT NULL,              -- JSON: context content
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,             -- System default (non-deletable)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_context_key (context_key),
    KEY idx_type_active (context_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usage tracking table (for subscription limits)
CREATE TABLE {prefix}aiagent_usage (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    usage_type VARCHAR(50) NOT NULL,             -- 'analysis', 'action', 'api_call'
    usage_date DATE NOT NULL,
    count INT(11) UNSIGNED DEFAULT 0,
    metadata LONGTEXT DEFAULT NULL,              -- JSON: additional data
    PRIMARY KEY (id),
    UNIQUE KEY idx_type_date (usage_type, usage_date),
    KEY idx_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scheduled tasks table
CREATE TABLE {prefix}aiagent_scheduled (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    task_type VARCHAR(50) NOT NULL,              -- 'analysis', 'action', 'price_change'
    task_data LONGTEXT NOT NULL,                 -- JSON: task parameters
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',        -- 'pending', 'running', 'completed', 'failed'
    executed_at DATETIME DEFAULT NULL,
    result LONGTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scheduled (scheduled_at, status),
    KEY idx_type (task_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```


---

## Admin Dashboard UI Requirements

### Dashboard Integration
The AI Agent module must integrate into the existing plugin's admin menu as a submenu or tab system. Do NOT create a separate top-level menu.

### Required Admin Pages

#### 1. Main Dashboard (`/admin.php?page=your-plugin-ai-agent`)
```
┌─────────────────────────────────────────────────────────────────────┐
│  AI Sales Agent Dashboard                              [Run Analysis]│
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────┐ │
│  │ Pending      │  │ Completed    │  │ Success      │  │ Today's  │ │
│  │ Actions      │  │ Today        │  │ Rate         │  │ Analyses │ │
│  │     24       │  │     156      │  │    94.2%     │  │    12    │ │
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────┘ │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ Recent Actions                                    [View All →]  ││
│  ├─────────────────────────────────────────────────────────────────┤│
│  │ Type          │ Entity      │ Priority │ Status  │ Actions     ││
│  │ create_discount│ Customer #42│ 85       │ Pending │ [Execute]   ││
│  │ send_email    │ Customer #18│ 72       │ Pending │ [Execute]   ││
│  │ update_product│ Product #156│ 68       │ Approved│ [Execute]   ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ Analysis Activity (Last 7 Days)                                 ││
│  │ [Chart: Line graph showing analyses and actions over time]      ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ Subscription Status                                             ││
│  │ Current Tier: Basic                                             ││
│  │ Analyses Today: 15/20  │  Actions Today: 42/50                  ││
│  │ [Upgrade Plan]                                                  ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

#### 2. Settings Page (`/admin.php?page=your-plugin-ai-agent-settings`)
Tabbed interface with sections:
- **General**: Module enable/disable, subscription tier display
- **LLM Configuration**: Provider, endpoint, API key, model, temperature
- **Analysis Settings**: Limits, thresholds, retention
- **Actions**: Enabled types, auto-execute, approval requirements
- **Schedule**: Frequency, preferred hours
- **Notifications**: Email settings, alert preferences
- **Advanced**: Rate limiting, debug options

#### 3. Analysis Results (`/admin.php?page=your-plugin-ai-agent-results`)
- Filterable table of all analysis results
- Filter by: type, status, date range, priority
- Expandable rows to view full analysis
- Export to CSV functionality

#### 4. Actions Management (`/admin.php?page=your-plugin-ai-agent-actions`)
- List of all actions with status
- Bulk actions: approve, execute, cancel
- Filter by: type, status, requires approval
- Action detail modal with full data

### UI Components Required

```php
// Admin view partials to create:
'partials/stats-card.php'           // Reusable stat card component
'partials/action-table.php'         // Actions table with pagination
'partials/analysis-table.php'       // Analysis results table
'partials/subscription-notice.php'  // Subscription status/upgrade prompt
'partials/feature-gate.php'         // "Feature requires upgrade" notice
'partials/settings-field.php'       // Dynamic settings field renderer
'partials/modal.php'                // Reusable modal component
'partials/chart.php'                // Chart.js wrapper
```

### CSS/JS Requirements
- Use WordPress admin styles as base
- Custom CSS in `assets/css/ai-agent-admin.css`
- JavaScript in `assets/js/ai-agent-admin.js`
- Use Chart.js for graphs (enqueue from CDN or bundle)
- AJAX for all actions (no page reloads)
- Loading states and error handling


---

## WP-CLI Commands

```bash
# Module management
wp aiagent status                    # Show module status and subscription info
wp aiagent enable                    # Enable the module
wp aiagent disable                   # Disable the module

# Analysis commands
wp aiagent analyze                   # Run full analysis (products + customers)
wp aiagent analyze --type=products   # Analyze products only
wp aiagent analyze --type=customers  # Analyze customers only
wp aiagent analyze --limit=10        # Limit entities to analyze

# Action commands
wp aiagent actions list              # List pending actions
wp aiagent actions execute           # Execute all pending actions
wp aiagent actions execute --id=123  # Execute specific action
wp aiagent actions approve --id=123  # Approve action for execution
wp aiagent actions cancel --id=123   # Cancel pending action

# Statistics
wp aiagent stats                     # Show statistics (default 30 days)
wp aiagent stats --days=7            # Show last 7 days
wp aiagent stats --format=json       # Output as JSON

# Settings
wp aiagent settings list             # List all settings
wp aiagent settings get <key>        # Get specific setting
wp aiagent settings set <key> <val>  # Set specific setting
wp aiagent settings reset            # Reset to defaults

# LLM testing
wp aiagent test-llm                  # Test LLM connection
wp aiagent test-llm --verbose        # Verbose output with response

# Maintenance
wp aiagent cleanup                   # Clean old analysis data
wp aiagent cleanup --days=30         # Clean data older than 30 days
wp aiagent migrate                   # Run database migrations
```


---

## System Prompts & Context

### Default System Prompts to Include

```php
$default_contexts = [
    'system_prompt' => [
        'role' => 'You are an expert WooCommerce sales optimization AI agent.',
        'objective' => 'Analyze store data to identify opportunities for increasing revenue.',
        'capabilities' => [
            'product_performance_analysis',
            'customer_lifecycle_optimization', 
            'pricing_strategy',
            'inventory_management',
            'churn_prediction'
        ],
        'response_format' => [
            'Always respond with valid JSON',
            'Include: analysis (string), suggestions (array), priority_score (1-100)',
            'Each suggestion must have: type, priority, data, reasoning'
        ],
        'available_actions' => [/* list of enabled actions */]
    ],
    
    'product_analysis_prompt' => [
        'instructions' => 'Analyze product performance and identify optimization opportunities.',
        'focus_areas' => [
            'sales_velocity',
            'pricing_effectiveness', 
            'inventory_efficiency',
            'competitive_positioning'
        ],
        'metrics_to_consider' => [
            'total_sales', 'conversion_rate', 'stock_levels',
            'price_vs_competitors', 'review_ratings', 'return_rate'
        ],
        'priority_guidelines' => [
            '90-100' => 'Critical - immediate action required',
            '70-89' => 'High - significant opportunity',
            '50-69' => 'Medium - worth implementing',
            '30-49' => 'Low - minor optimization',
            '1-29' => 'Minimal impact'
        ]
    ],
    
    'customer_analysis_prompt' => [
        'instructions' => 'Analyze customer behavior to maximize lifetime value.',
        'focus_areas' => [
            'purchase_patterns',
            'churn_risk',
            'upsell_opportunities',
            'engagement_level'
        ],
        'customer_segments' => [
            'new' => 'First-time buyers needing onboarding',
            'active' => 'Regular buyers for retention',
            'vip' => 'High-value customers for premium treatment',
            'at_risk' => 'Declining engagement, needs reactivation',
            'dormant' => 'Inactive, needs winback campaign'
        ]
    ]
];
```


---

## WordPress Hooks & Filters

### Actions to Fire

```php
// Module lifecycle
do_action('aiagent_module_activated');
do_action('aiagent_module_deactivated');

// Analysis events
do_action('aiagent_before_analysis', $type, $options);
do_action('aiagent_after_analysis', $type, $results);
do_action('aiagent_analysis_failed', $type, $error);

// Action events
do_action('aiagent_before_action_execute', $action_type, $action_data);
do_action('aiagent_after_action_execute', $action_type, $result);
do_action('aiagent_action_approved', $action_id, $user_id);
do_action('aiagent_action_failed', $action_type, $error);

// Settings events
do_action('aiagent_settings_updated', $key, $old_value, $new_value);
```

### Filters to Provide

```php
// Analysis customization
apply_filters('aiagent_analysis_context', $context, $type);
apply_filters('aiagent_products_query', $query_args);
apply_filters('aiagent_customers_query', $query_args);
apply_filters('aiagent_llm_prompt', $messages, $type, $entity);

// Action customization
apply_filters('aiagent_available_actions', $actions);
apply_filters('aiagent_action_priority', $priority, $action_type, $data);
apply_filters('aiagent_action_data', $data, $action_type);

// Settings
apply_filters('aiagent_settings_schema', $schema);
apply_filters('aiagent_setting_value', $value, $key);

// Subscription
apply_filters('aiagent_subscription_features', $features, $tier);
apply_filters('aiagent_usage_limit', $limit, $type, $tier);
```


---

## Security Requirements

### Input Validation
- Sanitize ALL user inputs using WordPress functions
- Validate settings against schema before saving
- Use prepared statements for ALL database queries
- Escape output using `esc_html()`, `esc_attr()`, `wp_kses_post()`

### Capability Checks
```php
// Required capabilities for different operations
'aiagent_view_dashboard'    => 'manage_woocommerce'
'aiagent_run_analysis'      => 'manage_woocommerce'
'aiagent_execute_actions'   => 'manage_woocommerce'
'aiagent_manage_settings'   => 'manage_options'
'aiagent_approve_actions'   => 'manage_woocommerce'
```

### AJAX Security
```php
// All AJAX handlers must:
check_ajax_referer('aiagent_nonce', 'nonce');
if (!current_user_can('manage_woocommerce')) {
    wp_send_json_error('Unauthorized', 403);
}
```

### SQL Analysis Security
- Only SELECT queries allowed
- Whitelist of allowed tables
- Query must include LIMIT clause
- Block dangerous keywords (DROP, DELETE, UPDATE, etc.)
- Validate table names against whitelist

### API Key Storage
- Encrypt API keys before storing in database
- Use WordPress transients for caching (not plain options for sensitive data)
- Never log API keys


---

## Integration with Parent Plugin

### Module Registration Pattern

```php
// In parent plugin's main service provider or bootstrap file:

class ParentPluginServiceProvider extends Provider
{
    public function register()
    {
        // Register AI Agent module if class exists
        if (class_exists('App\Modules\AIAgent\AIAgentModule')) {
            $this->registerModule(new \App\Modules\AIAgent\AIAgentModule());
        }
    }
    
    private function registerModule($module)
    {
        // Check if module should be active
        if ($module->shouldActivate()) {
            $module->register();
            
            // Store for boot phase
            $this->modules[] = $module;
        }
    }
    
    public function boot()
    {
        // Boot all registered modules
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }
}
```

### Admin Menu Integration

```php
// AI Agent should add its pages as children of parent plugin's menu
add_action('admin_menu', function() {
    // Assuming parent plugin has menu slug 'marketplace-api'
    
    add_submenu_page(
        'marketplace-api',              // Parent slug
        'AI Sales Agent',               // Page title
        'AI Sales Agent',               // Menu title
        'manage_woocommerce',           // Capability
        'marketplace-api-ai-agent',     // Menu slug
        [$controller, 'dashboardPage']  // Callback
    );
    
    add_submenu_page(
        'marketplace-api',
        'AI Agent Settings',
        'AI Settings',
        'manage_options',
        'marketplace-api-ai-settings',
        [$controller, 'settingsPage']
    );
});
```

### Shared Services
If parent plugin has services the AI Agent can use (like caching, logging), inject them:

```php
// In AIAgentModule::register()
\WPLite\Container::bind(CacheService::class, function() {
    // Try to use parent's cache service if available
    if (\WPLite\Container::has('parent.cache')) {
        return \WPLite\Container::resolve('parent.cache');
    }
    return new \App\Modules\AIAgent\Services\CacheService();
});
```


---

## Error Handling & Logging

### Logger Service

```php
<?php
namespace App\Modules\AIAgent\Services;

class Logger
{
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    
    /**
     * Log message with level
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = []);
    
    public function debug($message, array $context = []);
    public function info($message, array $context = []);
    public function warning($message, array $context = []);
    public function error($message, array $context = []);
}
```

### Error Response Format

```php
// All service methods should return consistent format:
[
    'success' => false,
    'error' => [
        'code' => 'ANALYSIS_FAILED',
        'message' => 'Human readable message',
        'details' => [] // Optional additional data
    ],
    'data' => null
]

// Success format:
[
    'success' => true,
    'error' => null,
    'data' => [/* result data */]
]
```

### Exception Handling

```php
// Custom exceptions
namespace App\Modules\AIAgent\Exceptions;

class AIAgentException extends \Exception {}
class LLMConnectionException extends AIAgentException {}
class SubscriptionLimitException extends AIAgentException {}
class ActionValidationException extends AIAgentException {}
class FeatureDisabledException extends AIAgentException {}
```


---

## Implementation Checklist

### Phase 1: Foundation
- [ ] Create module directory structure
- [ ] Implement `AIAgentModule` bootstrap class
- [ ] Create database migration system
- [ ] Implement `SettingsManager` with full schema
- [ ] Implement `SubscriptionManager` with tier system
- [ ] Create base configuration file

### Phase 2: Core Services
- [ ] Implement `LLMProviderInterface`
- [ ] Create `OllamaProvider` implementation
- [ ] Create `OpenAIProvider` implementation
- [ ] Implement `LLMFactory`
- [ ] Create `DatabaseService` for all DB operations
- [ ] Implement `CacheService`
- [ ] Implement `RateLimitService`

### Phase 3: Analysis System
- [ ] Implement `ProductAnalyzer`
- [ ] Implement `CustomerAnalyzer`
- [ ] Create `AIAgentService` orchestrator
- [ ] Build system prompts and context management
- [ ] Implement SQL analysis service (for Pro tier)

### Phase 4: Action System
- [ ] Create `AbstractAction` base class
- [ ] Implement all 10 action handlers
- [ ] Create `ActionExecutor` service
- [ ] Implement action approval workflow
- [ ] Add retry logic for failed actions

### Phase 5: Admin UI
- [ ] Create dashboard page with stats
- [ ] Build settings page with all sections
- [ ] Create analysis results page
- [ ] Build actions management page
- [ ] Implement all AJAX handlers
- [ ] Add CSS/JS assets

### Phase 6: CLI & Automation
- [ ] Implement all WP-CLI commands
- [ ] Set up WordPress cron schedules
- [ ] Add WooCommerce event triggers
- [ ] Implement scheduled task runner

### Phase 7: Polish
- [ ] Add all WordPress hooks and filters
- [ ] Implement notification system
- [ ] Add export functionality
- [ ] Security audit
- [ ] Performance optimization
- [ ] Documentation

---

## Code Style Guidelines

1. **PSR-4 Autoloading**: All classes in `App\Modules\AIAgent` namespace
2. **Type Hints**: Use PHPDoc for complex types (PHP 7.4 compatible)
3. **Return Types**: Document with `@return` annotations
4. **Dependency Injection**: Constructor injection for all services
5. **Single Responsibility**: One class = one purpose
6. **WordPress Standards**: Follow WordPress coding standards for hooks/filters
7. **No Global State**: Avoid global variables, use container
8. **Immutable Config**: Configuration should be read-only after boot

---

## Final Notes

- This module must work independently - if disabled, parent plugin functions normally
- All features must check subscription status before executing
- UI must gracefully show "upgrade required" for gated features
- Database tables use `{prefix}aiagent_` naming convention
- All strings should be translatable using `__()` and `_e()`
- Consider memory usage for large stores (batch processing)
- Implement proper cleanup on uninstall

The goal is a production-ready, enterprise-grade AI sales optimization module that can scale from small shops to large WooCommerce stores while maintaining clean, maintainable code.
