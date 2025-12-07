<?php

namespace Forooshyar\Providers;

use Forooshyar\Shortcodes\Hello;
use WPLite\Provider;


class ShortcodeServiceProvider extends Provider
{
    public function boot() {
        Hello::register();
    }
}