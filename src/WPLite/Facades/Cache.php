<?php

namespace Forooshyar\WPLite\Facades;

use Forooshyar\WPLite\Cache\CacheManager;


/**
 * @method static \Forooshyar\WPLite\Application make($class, array $params = [])
 * @see \Forooshyar\WPLite\Application
**/
class Cache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return CacheManager::class;
    }
}