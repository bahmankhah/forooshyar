<?php

return [
    'plugin' => [
        'name' => 'Forooshyar',
        'version' => '2.0.0',
        'text_domain' => 'forooshyar',
        'domain_path' => '/languages',
        'plugin_file' => 'forooshyar/forooshyar.php',
        'min_wp_version' => '5.0',
        'min_wc_version' => '4.0',
        'tested_wp_version' => '6.4'
    ],
    'admin' => [
        'menu_position' => 58,
        'capability' => 'manage_options',
        'icon' => 'dashicons-store',
        'pages' => [
            'settings' => [
                'page_title' => __('Forooshyar Settings', 'forooshyar'),
                'menu_title' => __('Forooshyar', 'forooshyar'),
                'slug' => 'forooshyar-settings'
            ],
            'monitor' => [
                'page_title' => __('API Monitor', 'forooshyar'),
                'menu_title' => __('API Monitor', 'forooshyar'),
                'slug' => 'forooshyar-monitor',
                'parent' => 'forooshyar-settings'
            ]
        ]
    ],
    'api' => [
        'namespace' => 'forooshyar/v1',
        'endpoints' => [
            'products' => '/products',
            'product' => '/products/{id}',
            'products_by_ids' => '/products/by-ids',
            'products_by_slugs' => '/products/by-slugs'
        ]
    ]
];