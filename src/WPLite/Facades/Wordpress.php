<?php

namespace Forooshyar\WPLite\Facades;

class Wordpress extends Facade{

    protected static function getFacadeAccessor() {
        return \Forooshyar\WPLite\WordpressManager::class;
    }
}