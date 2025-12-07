<?php

// Mock WordPress functions for testing
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }
}

if (!function_exists('sanitize_url')) {
    function sanitize_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

// Mock WordPress transient functions for caching
class GlobalTransientMock {
    private static $transients = [];
    
    public static function get($transient) {
        return self::$transients[$transient] ?? false;
    }
    
    public static function set($transient, $value, $expiration = 0) {
        self::$transients[$transient] = $value;
        return true;
    }
    
    public static function delete($transient) {
        if (isset(self::$transients[$transient])) {
            unset(self::$transients[$transient]);
            return true;
        }
        return false;
    }
    
    public static function clear() {
        self::$transients = [];
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return GlobalTransientMock::get($transient);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return GlobalTransientMock::set($transient, $value, $expiration);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return GlobalTransientMock::delete($transient);
    }
}