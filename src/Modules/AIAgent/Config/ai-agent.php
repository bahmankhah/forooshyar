<?php
/**
 * AI Agent Module Configuration
 * 
 * @package Forooshyar\Modules\AIAgent
 */

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
                'features' => [
                    'product_analysis',
                    'customer_analysis',
                    'auto_actions',
                    'sql_analysis',
                    'advanced_reports'
                ],
                'limits' => [
                    'analyses_per_day' => 100,
                    'actions_per_day' => 500,
                    'products_per_analysis' => 200,
                    'customers_per_analysis' => 500,
                ],
                'llm_providers' => ['ollama', 'openai', 'anthropic'],
            ],
            'enterprise' => [
                'features' => ['*'],
                'limits' => [
                    'analyses_per_day' => -1,
                    'actions_per_day' => -1,
                    'products_per_analysis' => -1,
                    'customers_per_analysis' => -1,
                ],
                'llm_providers' => ['*'],
            ],
        ],
    ],

    'settings_schema' => [
        // Module Settings
        'module_enabled' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'Enable AI Sales Agent Module',
            'section' => 'general',
        ],

        // LLM Provider Settings
        'llm_provider' => [
            'type' => 'select',
            'default' => 'ollama',
            'options' => [
                'ollama' => 'Ollama (Local)',
                'openai' => 'OpenAI',
                'anthropic' => 'Anthropic Claude',
            ],
            'label' => 'LLM Provider',
            'section' => 'llm',
            'description' => 'Select your preferred LLM provider. Ollama runs locally and is free.',
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
        'llm_organization' => [
            'type' => 'text',
            'default' => '',
            'label' => 'OpenAI Organization ID',
            'section' => 'llm',
            'description' => 'Optional. Only for OpenAI provider.',
            'show_if' => ['llm_provider' => 'openai'],
        ],
        'llm_json_mode' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Use JSON Mode',
            'section' => 'llm',
            'description' => 'Request structured JSON responses when supported.',
        ],
        'llm_retry_attempts' => [
            'type' => 'number',
            'default' => 3,
            'min' => 0,
            'max' => 5,
            'label' => 'LLM Retry Attempts',
            'section' => 'llm',
            'description' => 'Number of retries on LLM API failures.',
        ],
        'llm_retry_delay' => [
            'type' => 'number',
            'default' => 1000,
            'min' => 100,
            'max' => 10000,
            'label' => 'Retry Delay (ms)',
            'section' => 'llm',
            'description' => 'Delay between retry attempts in milliseconds.',
        ],

        // Analysis Settings
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

        // Action Settings
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
                'send_email',
                'send_sms',
                'create_discount',
                'update_product',
                'create_campaign',
                'schedule_followup',
                'create_bundle',
                'inventory_alert',
                'loyalty_reward',
                'schedule_price_change'
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

        // Schedule Settings
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

        // Notification Settings
        'notify_admin_email' => [
            'type' => 'email',
            'default' => '',
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

        // Rate Limiting
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

        // Debug Settings
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
    ],

    'default_contexts' => [
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
        ],

        'product_analysis_prompt' => [
            'instructions' => 'Analyze product performance and identify optimization opportunities.',
            'focus_areas' => [
                'sales_velocity',
                'pricing_effectiveness',
                'inventory_efficiency',
                'competitive_positioning'
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
    ],
];
