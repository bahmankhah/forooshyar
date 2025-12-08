<?php

use Forooshyar\Providers\AppServiceProvider;
use Forooshyar\Providers\ShortcodeServiceProvider;


return [
    'global_middlewares'=>[

    ],
    'providers'=>[
        AppServiceProvider::class,
    ],
    'version'=>'v1',
    'name'=>'donapp-core',
    'url'=>getenv('APP_URL'),
    'api'=> [
        'namespace'=>'wplite/v1',
    ],
];