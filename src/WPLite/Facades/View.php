<?php

namespace Forooshyar\WPLite\Facades;

class View extends Facade{

    protected static function getFacadeAccessor() {
        return \Forooshyar\WPLite\ViewManager::class;
    }
}