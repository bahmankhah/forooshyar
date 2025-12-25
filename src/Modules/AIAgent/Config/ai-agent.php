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
                'name' => 'رایگان',
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
                'name' => 'پایه',
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
                'name' => 'حرفه‌ای',
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
                'name' => 'سازمانی',
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
            'label' => 'فعال‌سازی ماژول دستیار فروش هوشمند',
            'section' => 'general',
            'description' => 'با فعال‌سازی این گزینه، ماژول هوش مصنوعی فروش شروع به کار می‌کند.',
        ],

        // LLM Provider Settings
        'llm_provider' => [
            'type' => 'select',
            'default' => 'ollama',
            'options' => [
                'ollama' => 'Ollama (محلی)',
                'openai' => 'OpenAI',
                'anthropic' => 'Anthropic Claude',
            ],
            'label' => 'ارائه‌دهنده هوش مصنوعی',
            'section' => 'llm',
            'description' => 'ارائه‌دهنده مدل زبانی را انتخاب کنید. Ollama به صورت محلی و رایگان اجرا می‌شود.',
        ],
        'llm_endpoint' => [
            'type' => 'url',
            'default' => 'http://localhost:11434/api/generate',
            'label' => 'آدرس API',
            'section' => 'llm',
            'description' => 'آدرس نقطه پایانی API مدل زبانی',
        ],
        'llm_api_key' => [
            'type' => 'password',
            'default' => '',
            'label' => 'کلید API',
            'section' => 'llm',
            'encrypted' => true,
            'description' => 'کلید API برای OpenAI یا Anthropic (برای Ollama نیازی نیست)',
        ],
        'llm_model' => [
            'type' => 'text',
            'default' => 'llama2',
            'label' => 'نام مدل',
            'section' => 'llm',
            'description' => 'نام مدل هوش مصنوعی (مثال: llama2، gpt-4، claude-3-sonnet)',
        ],
        'llm_temperature' => [
            'type' => 'number',
            'default' => 0.7,
            'min' => 0,
            'max' => 2,
            'step' => 0.1,
            'label' => 'دمای مدل',
            'section' => 'llm',
            'description' => 'مقدار بالاتر = پاسخ‌های خلاقانه‌تر، مقدار پایین‌تر = پاسخ‌های دقیق‌تر',
        ],
        'llm_max_tokens' => [
            'type' => 'number',
            'default' => 2000,
            'min' => 100,
            'max' => 8000,
            'label' => 'حداکثر توکن',
            'section' => 'llm',
            'description' => 'حداکثر تعداد توکن در هر پاسخ',
        ],
        'llm_timeout' => [
            'type' => 'number',
            'default' => 60,
            'min' => 10,
            'max' => 300,
            'label' => 'مهلت زمانی (ثانیه)',
            'section' => 'llm',
            'description' => 'حداکثر زمان انتظار برای پاسخ API',
        ],
        'llm_organization' => [
            'type' => 'text',
            'default' => '',
            'label' => 'شناسه سازمان OpenAI',
            'section' => 'llm',
            'description' => 'اختیاری. فقط برای OpenAI',
            'show_if' => ['llm_provider' => 'openai'],
        ],
        'llm_json_mode' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'حالت JSON',
            'section' => 'llm',
            'description' => 'درخواست پاسخ‌های ساختاریافته JSON (در صورت پشتیبانی)',
        ],
        'llm_retry_attempts' => [
            'type' => 'number',
            'default' => 3,
            'min' => 0,
            'max' => 5,
            'label' => 'تعداد تلاش مجدد',
            'section' => 'llm',
            'description' => 'تعداد تلاش‌های مجدد در صورت خطای API',
        ],
        'llm_retry_delay' => [
            'type' => 'number',
            'default' => 1000,
            'min' => 100,
            'max' => 10000,
            'label' => 'تأخیر تلاش مجدد (میلی‌ثانیه)',
            'section' => 'llm',
            'description' => 'فاصله زمانی بین تلاش‌های مجدد',
        ],

        // Analysis Settings
        'analysis_product_limit' => [
            'type' => 'number',
            'default' => 50,
            'min' => 1,
            'max' => 500,
            'label' => 'تعداد محصولات در هر تحلیل',
            'section' => 'analysis',
            'description' => 'حداکثر تعداد محصولاتی که در هر بار تحلیل بررسی می‌شوند',
        ],
        'analysis_customer_limit' => [
            'type' => 'number',
            'default' => 100,
            'min' => 1,
            'max' => 1000,
            'label' => 'تعداد مشتریان در هر تحلیل',
            'section' => 'analysis',
            'description' => 'حداکثر تعداد مشتریانی که در هر بار تحلیل بررسی می‌شوند',
        ],
        'analysis_priority_threshold' => [
            'type' => 'number',
            'default' => 70,
            'min' => 1,
            'max' => 100,
            'label' => 'آستانه اولویت اجرای خودکار',
            'section' => 'analysis',
            'description' => 'اقداماتی با اولویت بالاتر از این مقدار به صورت خودکار اجرا می‌شوند',
        ],
        'analysis_retention_days' => [
            'type' => 'number',
            'default' => 90,
            'min' => 7,
            'max' => 365,
            'label' => 'مدت نگهداری داده‌ها (روز)',
            'section' => 'analysis',
            'description' => 'داده‌های تحلیل قدیمی‌تر از این مدت حذف می‌شوند',
        ],
        'analysis_enable_sql' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'فعال‌سازی تحلیل SQL',
            'section' => 'analysis',
            'requires_feature' => 'sql_analysis',
            'description' => 'تحلیل پیشرفته با کوئری‌های SQL مستقیم',
        ],

        // Action Settings
        'actions_auto_execute' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'اجرای خودکار اقدامات با اولویت بالا',
            'section' => 'actions',
            'description' => 'اقدامات با اولویت بالا بدون نیاز به تأیید اجرا شوند',
        ],
        'actions_max_per_run' => [
            'type' => 'number',
            'default' => 10,
            'min' => 1,
            'max' => 100,
            'label' => 'حداکثر اقدامات در هر اجرا',
            'section' => 'actions',
            'description' => 'حداکثر تعداد اقداماتی که در هر بار اجرا می‌شوند',
        ],
        'actions_retry_failed' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'تلاش مجدد برای اقدامات ناموفق',
            'section' => 'actions',
            'description' => 'اقدامات ناموفق دوباره تلاش شوند',
        ],
        'actions_retry_attempts' => [
            'type' => 'number',
            'default' => 3,
            'min' => 1,
            'max' => 10,
            'label' => 'تعداد تلاش مجدد',
            'section' => 'actions',
            'description' => 'حداکثر تعداد تلاش برای اقدامات ناموفق',
        ],
        'actions_enabled_types' => [
            'type' => 'multiselect',
            'default' => ['send_email', 'create_discount'],
            'options' => [
                'send_email' => 'ارسال ایمیل',
                'send_sms' => 'ارسال پیامک',
                'create_discount' => 'ایجاد تخفیف',
                'update_product' => 'بروزرسانی محصول',
                'create_campaign' => 'ایجاد کمپین',
                'schedule_followup' => 'زمان‌بندی پیگیری',
                'create_bundle' => 'ایجاد بسته',
                'inventory_alert' => 'هشدار موجودی',
                'loyalty_reward' => 'پاداش وفاداری',
                'schedule_price_change' => 'زمان‌بندی تغییر قیمت',
            ],
            'label' => 'انواع اقدامات فعال',
            'section' => 'actions',
            'description' => 'انواع اقداماتی که سیستم می‌تواند پیشنهاد و اجرا کند',
        ],
        'actions_require_approval' => [
            'type' => 'multiselect',
            'default' => ['create_discount', 'update_product'],
            'options' => [
                'create_discount' => 'ایجاد تخفیف',
                'update_product' => 'بروزرسانی محصول',
                'schedule_price_change' => 'زمان‌بندی تغییر قیمت',
            ],
            'label' => 'اقدامات نیازمند تأیید',
            'section' => 'actions',
            'description' => 'این اقدامات قبل از اجرا نیاز به تأیید دستی دارند',
        ],

        // Schedule Settings
        'schedule_frequency' => [
            'type' => 'select',
            'default' => 'daily',
            'options' => [
                'hourly' => 'هر ساعت',
                'twice_daily' => 'دو بار در روز',
                'daily' => 'روزانه',
                'weekly' => 'هفتگی',
                'manual' => 'دستی',
            ],
            'label' => 'تناوب تحلیل',
            'section' => 'schedule',
            'description' => 'هر چند وقت یکبار تحلیل خودکار اجرا شود',
        ],
        'schedule_preferred_hours' => [
            'type' => 'multiselect',
            'default' => [9, 14],
            'options' => range(0, 23),
            'label' => 'ساعات ترجیحی تحلیل',
            'section' => 'schedule',
            'description' => 'ساعاتی که تحلیل ترجیحاً در آن‌ها اجرا شود',
        ],
        'schedule_avoid_hours' => [
            'type' => 'multiselect',
            'default' => [0, 1, 2, 3, 4, 5],
            'options' => range(0, 23),
            'label' => 'ساعات ممنوع',
            'section' => 'schedule',
            'description' => 'ساعاتی که تحلیل در آن‌ها اجرا نشود',
        ],

        // Notification Settings
        'notify_admin_email' => [
            'type' => 'email',
            'default' => '',
            'label' => 'ایمیل اعلان‌ها',
            'section' => 'notifications',
            'description' => 'ایمیلی که اعلان‌ها به آن ارسال می‌شود (پیش‌فرض: ایمیل مدیر)',
        ],
        'notify_on_high_priority' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'اعلان اقدامات با اولویت بالا',
            'section' => 'notifications',
            'description' => 'هنگام شناسایی اقدامات با اولویت بالا اعلان ارسال شود',
        ],
        'notify_on_errors' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'اعلان خطاها',
            'section' => 'notifications',
            'description' => 'هنگام بروز خطا در سیستم اعلان ارسال شود',
        ],
        'notify_daily_summary' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'خلاصه روزانه',
            'section' => 'notifications',
            'description' => 'ارسال خلاصه فعالیت‌های روزانه',
        ],

        // Rate Limiting
        'rate_limit_per_hour' => [
            'type' => 'number',
            'default' => 100,
            'min' => 10,
            'max' => 1000,
            'label' => 'درخواست‌های API در ساعت',
            'section' => 'rate_limiting',
            'description' => 'حداکثر تعداد درخواست به API در هر ساعت',
        ],
        'rate_limit_per_day' => [
            'type' => 'number',
            'default' => 1000,
            'min' => 100,
            'max' => 10000,
            'label' => 'درخواست‌های API در روز',
            'section' => 'rate_limiting',
            'description' => 'حداکثر تعداد درخواست به API در هر روز',
        ],

        // Debug Settings
        'debug_enabled' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'فعال‌سازی حالت اشکال‌زدایی',
            'section' => 'debug',
            'description' => 'ثبت اطلاعات تفصیلی برای اشکال‌زدایی',
        ],
        'debug_log_level' => [
            'type' => 'select',
            'default' => 'error',
            'options' => [
                'debug' => 'اشکال‌زدایی',
                'info' => 'اطلاعات',
                'warning' => 'هشدار',
                'error' => 'خطا',
            ],
            'label' => 'سطح لاگ',
            'section' => 'debug',
            'description' => 'حداقل سطح پیام‌هایی که ثبت می‌شوند',
        ],
        'debug_save_prompts' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'ذخیره پرامپت‌های LLM',
            'section' => 'debug',
            'description' => 'ذخیره پرامپت‌های ارسالی به مدل زبانی در لاگ',
        ],
    ],

    'default_contexts' => [
        'system_prompt' => [
            'role' => 'شما یک دستیار هوشمند بهینه‌سازی فروش ووکامرس هستید.',
            'objective' => 'تحلیل داده‌های فروشگاه برای شناسایی فرصت‌های افزایش درآمد.',
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
            'instructions' => 'تحلیل عملکرد محصول و شناسایی فرصت‌های بهینه‌سازی.',
            'focus_areas' => [
                'sales_velocity',
                'pricing_effectiveness',
                'inventory_efficiency',
                'competitive_positioning'
            ],
            'priority_guidelines' => [
                '90-100' => 'بحرانی - نیاز به اقدام فوری',
                '70-89' => 'بالا - فرصت قابل توجه',
                '50-69' => 'متوسط - ارزش پیاده‌سازی',
                '30-49' => 'پایین - بهینه‌سازی جزئی',
                '1-29' => 'تأثیر حداقلی'
            ]
        ],

        'customer_analysis_prompt' => [
            'instructions' => 'تحلیل رفتار مشتری برای حداکثرسازی ارزش طول عمر.',
            'focus_areas' => [
                'purchase_patterns',
                'churn_risk',
                'upsell_opportunities',
                'engagement_level'
            ],
            'customer_segments' => [
                'new' => 'خریداران جدید - نیاز به آشناسازی',
                'active' => 'خریداران فعال - حفظ مشتری',
                'vip' => 'مشتریان ویژه - رفتار ممتاز',
                'at_risk' => 'در معرض ریزش - نیاز به فعال‌سازی مجدد',
                'dormant' => 'غیرفعال - نیاز به کمپین بازگشت'
            ]
        ]
    ],
];
