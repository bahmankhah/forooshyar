<?php

namespace Forooshyar\WPLite\Facades;

use Forooshyar\WPLite\Application;
use Forooshyar\WPLite\Auth\AuthManager;

/**
 * @method static \Forooshyar\WPLite\Application make($class, array $params = [])
 * @see \Forooshyar\WPLite\Application
**/
class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return AuthManager::class;
    }
}