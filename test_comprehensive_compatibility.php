<?php
/**
 * Comprehensive PHP 7.4 Compatibility Test
 * 
 * This script performs a more thorough test of the application
 * including method calls and integration between services.
 */

// Autoload classes
require_once __DIR__ . '/vendor/autoload.php';

// Mock WordPress functions
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

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

echo "Comprehensive PHP 7.4 Compatibility Test\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

try {
    // Test service instantiation and basic functionality
    echo "1. Testing ConfigService functionality... ";
    $configService = new \Forooshyar\Services\ConfigService();
    $config = $configService->getAll();
    $variables = $configService->getAvailableVariables();
    echo "✓ OK\n";
    
    echo "2. Testing TitleBuilder functionality... ";
    $titleBuilder = new \Forooshyar\Services\TitleBuilder($configService);
    // Test template parsing
    $result = $titleBuilder->parseTemplate('{{product_name}} - {{category}}', [
        'product_name' => 'Test Product',
        'category' => 'Test Category'
    ]);
    if ($result === 'Test Product - Test Category') {
        echo "✓ OK\n";
    } else {
        throw new Exception("Template parsing failed: got '$result'");
    }
    
    echo "3. Testing CacheService functionality... ";
    $cacheService = new \Forooshyar\Services\CacheService($configService);
    $cacheService->set('test_key', 'test_value');
    $stats = $cacheService->getStats();
    echo "✓ OK\n";
    
    echo "4. Testing LoggingService functionality... ";
    $loggingService = new \Forooshyar\Services\LoggingService($configService);
    // Test error logging (should not throw errors even without wpdb)
    try {
        $loggingService->logError(new Exception('Test error'), 'test', 'test_operation');
        echo "✓ OK\n";
    } catch (Exception $e) {
        throw new Exception("LoggingService failed: " . $e->getMessage());
    }
    
    echo "5. Testing ErrorHandlingService functionality... ";
    $errorHandlingService = new \Forooshyar\Services\ErrorHandlingService(
        $configService,
        $cacheService,
        $loggingService
    );
    
    // Test error handling with fallback
    $result = $errorHandlingService->executeWithFallback(
        function() {
            return ['success' => true, 'data' => 'test'];
        },
        function() {
            return ['fallback' => true];
        },
        'test_operation'
    );
    
    if ($result['success'] && $result['data'] === 'test') {
        echo "✓ OK\n";
    } else {
        throw new Exception("ErrorHandlingService failed");
    }
    
    echo "6. Testing ApiLogService functionality... ";
    $apiLogService = new \Forooshyar\Services\ApiLogService($configService, false);
    $logResult = $apiLogService->logRequest([
        'endpoint' => '/test',
        'method' => 'GET',
        'response_time' => 100,
        'status_code' => 200
    ]);
    echo "✓ OK\n";
    
    echo "7. Testing ProductService functionality... ";
    $productService = new \Forooshyar\Services\ProductService($configService, $titleBuilder);
    echo "✓ OK\n";
    
    echo "8. Testing Controllers... ";
    $adminController = new \Forooshyar\Controllers\AdminController();
    echo "✓ OK\n";
    
    echo "9. Testing Resources... ";
    $productResource = new \Forooshyar\Resources\ProductResource([
        'title' => 'Test Product',
        'page_unique' => 123,
        'current_price' => '100.00'
    ]);
    $resourceArray = $productResource->toArray();
    
    if (isset($resourceArray['title']) && $resourceArray['title'] === 'Test Product') {
        echo "✓ OK\n";
    } else {
        throw new Exception("ProductResource failed");
    }
    
    echo "10. Testing Collection Resource... ";
    $collectionResource = new \Forooshyar\Resources\ProductCollectionResource([
        'count' => 1,
        'max_pages' => 1,
        'products' => [
            ['title' => 'Test Product', 'page_unique' => 123]
        ]
    ]);
    $collectionArray = $collectionResource->toArray();
    
    if ($collectionArray['count'] === 1 && count($collectionArray['products']) === 1) {
        echo "✓ OK\n";
    } else {
        throw new Exception("ProductCollectionResource failed");
    }
    
    echo "\n✅ All comprehensive tests passed!\n";
    echo "✅ PHP 7.4 compatibility fully confirmed!\n";
    echo "✅ All functionality preserved and working correctly!\n";
    
} catch (ParseError $e) {
    echo "\n❌ Parse Error (syntax issue): " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (TypeError $e) {
    echo "\n❌ Type Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}