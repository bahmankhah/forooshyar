<?php

namespace Forooshyar\WPLite\Facades;

class Route extends Facade{

    protected static function getFacadeAccessor() {
        return \Forooshyar\WPLite\RouteManager::class;
    }
}