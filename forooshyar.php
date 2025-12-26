<?php

use Forooshyar\WPLite\Facades\App;
use function Forooshyar\WPLite\appLogger;
/**
 * Plugin Name: فروشیار
 * Description: WPLite Powered Wordpress Plugin.
 * Version: 2.0.0
 * Author: Hesam
 */

if (!defined('ABSPATH')) exit;
require __DIR__ . '/vendor/autoload.php';

App::setPluginFile(__FILE__);
App::setPluginPath(plugin_dir_path(__FILE__));
appLogger('BOOTING MAIN FOROOSHYAR');
App::boot();
