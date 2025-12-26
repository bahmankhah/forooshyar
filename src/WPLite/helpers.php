<?php

/**
 * WPLite Helpers Loader
 * 
 * Include this file to load helper functions.
 * Classes are autoloaded via Composer's PSR-4.
 * 
 * @generated Do not edit. Run `php vendor/hsm/wplite/wplite build` to regenerate.
 */

foreach (glob(__DIR__ . '/Helpers/*.php') as $file) {
    require_once $file;
}
