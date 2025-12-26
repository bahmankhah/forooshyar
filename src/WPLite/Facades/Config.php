<?php

namespace Forooshyar\WPLite\Facades;

class Config extends Facade{

    protected static function getFacadeAccessor() {
        return \Forooshyar\WPLite\Config::class;
    }
}