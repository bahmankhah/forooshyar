<?php
/**
 * Test Route Registration Process
 */

// Mock WordPress functions that are needed
if (!function_exists('add_action')) {
    $wp_actions = [];
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_actions;
        if (!isset($wp_actions[$hook])) {
            $wp_actions[$hook] = [];
        }
        $wp_actions[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'args' => $accepted_args
        ];
        echo "✓ Action registered: $hook\n";
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {
        echo "✓ REST route registered: $namespace$route\n";
        return true;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

// Load the framework
require_once __DIR__ . '/vendor/autoload.php';

echo "Testing Route Registration Process...\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

try {
    // Set up the application
    $app = new \Forooshyar\WPLite\Application();
    $app->setPluginPath(__DIR__ . '/');
    $app->setPluginFile(__FILE__);
    
    echo "1. Loading configuration...\n";
    \Forooshyar\WPLite\Config::load();
    echo "✓ Configuration loaded\n\n";
    
    echo "2. Testing appConfig function...\n";
    $namespace = appConfig('app.api.namespace', 'default/v1');
    echo "✓ API namespace: $namespace\n\n";
    
    echo "3. Testing RouteDefinition creation...\n";
    $route = new \Forooshyar\WPLite\RouteDefinition('GET', '/products', [\Forooshyar\Controllers\ProductController::class, 'index'], 'rest');
    echo "✓ RouteDefinition created\n\n";
    
    echo "4. Testing route registration...\n";
    $route->make();
    echo "✓ Route make() called\n\n";
    
    echo "5. Testing controller instantiation...\n";
    $controller = new \Forooshyar\Controllers\ProductController();
    echo "✓ ProductController instantiated\n\n";
    
    echo "6. Testing Pipeline...\n";
    $pipeline = new \Forooshyar\WPLite\Pipeline();
    echo "✓ Pipeline created\n\n";
    
    echo "✅ All route registration components work!\n";
    
} catch (Throwable $e) {
    echo "❌ Route registration test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}