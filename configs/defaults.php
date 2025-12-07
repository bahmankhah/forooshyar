<?php

return [
    'config' => [
        'general' => [
            'show_variations' => true,
            'title_template' => '{{product_name}}{{variation_suffix}}',
            'custom_suffix' => '',
            'language' => 'fa_IR'
        ],
        'fields' => [
            'title' => true,
            'subtitle' => true,
            'parent_id' => true,
            'page_unique' => true,
            'current_price' => true,
            'old_price' => true,
            'availability' => true,
            'category_name' => true,
            'image_links' => true,
            'image_link' => true,
            'page_url' => true,
            'short_desc' => true,
            'spec' => true,
            'date' => true,
            'registry' => true,
            'guarantee' => true
        ],
        'images' => [
            'sizes' => ['thumbnail', 'medium', 'large', 'full'],
            'max_images' => 10,
            'quality' => 80
        ],
        'cache' => [
            'enabled' => true,
            'ttl' => 3600,
            'auto_invalidate' => true
        ],
        'api' => [
            'max_per_page' => 100,
            'rate_limit' => 1000,
            'timeout' => 30,
            'logging' => [
                'enabled' => true,
                'log_requests' => true,
                'log_responses' => false,
                'log_errors' => true,
                'log_performance' => true
            ]
        ],
        'advanced' => [
            'log_retention' => 30,
            'cleanup_enabled' => true,
            'cleanup_interval' => 'daily',
            'rate_limit_enabled' => true,
            'performance_monitoring' => true
        ]
    ],
    'template_variables' => [
        'product_name' => 'نام محصول',
        'variation_name' => 'نام تنوع',
        'category' => 'دسته‌بندی',
        'sku' => 'کد محصول',
        'brand' => 'برند',
        'custom_suffix' => 'پسوند سفارشی',
        'variation_suffix' => 'پسوند تنوع'
    ]
];