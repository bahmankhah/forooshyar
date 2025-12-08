<?php

use WPLite\Facades\App;
/**
 * Plugin Name: WPLite Plugin
 * Description: WPLite Powered Wordpress Plugin.
 * Version: 1.0
 * Author: Hesam
 */

if (!defined('ABSPATH')) exit;
require __DIR__ . '/vendor/autoload.php';
App::setPluginFile(__FILE__);
App::setPluginPath(plugin_dir_path(__FILE__));
App::boot();
