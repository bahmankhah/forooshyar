<?php

use Forooshyar\Providers\AppServiceProvider;


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
        'namespace'=>'forooshyar/v1',
    ],
];