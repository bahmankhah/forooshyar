<?php

return [
    'text_domain' => 'forooshyar',
    'languages_path' => plugin_dir_path(__FILE__) . '../languages/',
    'default_locale' => 'fa_IR',
    'supported_locales' => [
        'fa_IR' => 'فارسی',
        'en_US' => 'English'
    ],
    'rtl_locales' => ['fa_IR'],
    'date_formats' => [
        'fa_IR' => [
            'format' => 'Y/m/d',
            'calendar' => 'jalali'
        ],
        'en_US' => [
            'format' => 'Y-m-d',
            'calendar' => 'gregorian'
        ]
    ]
];