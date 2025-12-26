<?php

namespace Forooshyar\WPLite\Facades;

use Forooshyar\WPLite\Application;

/**
 * @method static \Forooshyar\WPLite\Application make($class, array $params = [])
 * @see \Forooshyar\WPLite\Application
**/
class App extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Application::class;
    }
}