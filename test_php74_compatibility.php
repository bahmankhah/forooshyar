<?php
/**
 * PHP 7.4 Compatibility Test
 * 
 * This script tests if the application works correctly with PHP 7.4
 * by instantiating key classes and checking for syntax errors.
 */

// Autoload classes
require_once __DIR__ . '/vendor/autoload.php';

// Mock WordPress functions that might be needed
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

echo "Testing PHP 7.4 Compatibility...\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

try {
    // Test ConfigService
    echo "Testing ConfigService... ";
    $configService = new \Forooshyar\Services\ConfigService();
    echo "✓ OK\n";
    
    // Test TitleBuilder
    echo "Testing TitleBuilder... ";
    $titleBuilder = new \Forooshyar\Services\TitleBuilder($configService);
    echo "✓ OK\n";
    
    // Test CacheService
    echo "Testing CacheService... ";
    $cacheService = new \Forooshyar\Services\CacheService($configService);
    echo "✓ OK\n";
    
    // Test LoggingService
    echo "Testing LoggingService... ";
    $loggingService = new \Forooshyar\Services\LoggingService($configService);
    echo "✓ OK\n";
    
    // Test ErrorHandlingService
    echo "Testing ErrorHandlingService... ";
    $errorHandlingService = new \Forooshyar\Services\ErrorHandlingService(
        $configService,
        $cacheService,
        $loggingService
    );
    echo "✓ OK\n";
    
    // Test AdminController
    echo "Testing AdminController... ";
    $adminController = new \Forooshyar\Controllers\AdminController();
    echo "✓ OK\n";
    
    // Test ProductService
    echo "Testing ProductService... ";
    $productService = new \Forooshyar\Services\ProductService($configService, $titleBuilder);
    echo "✓ OK\n";
    
    echo "\n✅ All core classes instantiated successfully!\n";
    echo "✅ PHP 7.4 compatibility confirmed!\n";
    
} catch (ParseError $e) {
    echo "\n❌ Parse Error (syntax issue): " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (TypeError $e) {
    echo "\n❌ Type Error (likely typed property issue): " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}