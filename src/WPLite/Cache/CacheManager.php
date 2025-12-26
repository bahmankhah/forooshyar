<?php

namespace Forooshyar\WPLite\Cache;

use Forooshyar\WPLite\Adapters\AdapterManager;


class CacheManager extends AdapterManager
{

    public function getKey(): string{
        return 'cache';
    }

}
