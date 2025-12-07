<?php
/**
 * Simple test script for advanced configuration features
 */

// Mock WordPress functions for testing
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) { return true; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data) { echo json_encode(['success' => true, 'data' => $data]); }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data) { echo json_encode(['success' => false, 'data' => $data]); }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}
if (!function_exists('get_site_url')) {
    function get_site_url() { return 'https://example.com'; }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show) { return '6.0'; }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) { return $default; }
}
if (!function_exists('update_option')) {
    function update_option($option, $value) { return true; }
}

require_once __DIR__ . '/vendor/autoload.php';

use Forooshyar\Services\ConfigService;
use Forooshyar\Controllers\AdminController;

echo "Testing Advanced Configuration Features...\n\n";

// Test 1: ConfigService export functionality
echo "1. Testing ConfigService export...\n";
try {
    $configService = new ConfigService();
    $exportData = $configService->export();
    
    if (isset($exportData['config']) && isset($exportData['exported_at'])) {
        echo "✓ Export functionality works\n";
    } else {
        echo "✗ Export missing required fields\n";
    }
} catch (Exception $e) {
    echo "✗ Export failed: " . $e->getMessage() . "\n";
}

// Test 2: ConfigService import functionality
echo "\n2. Testing ConfigService import...\n";
try {
    $configService = new ConfigService();
    $testConfig = [
        'config' => [
            'general' => ['show_variations' => false],
            'fields' => ['title' => true],
            'images' => ['max_images' => 5],
            'cache' => ['enabled' => true],
            'api' => ['max_per_page' => 50],
            'advanced' => ['log_retention' => 15]
        ]
    ];
    
    $result = $configService->import($testConfig);
    
    if ($result) {
        echo "✓ Import functionality works\n";
    } else {
        echo "✗ Import failed\n";
    }
} catch (Exception $e) {
    echo "✗ Import failed: " . $e->getMessage() . "\n";
}

// Test 3: AdminController backup creation
echo "\n3. Testing AdminController backup creation...\n";
try {
    $adminController = new AdminController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($adminController);
    $method = $reflection->getMethod('createCompleteBackup');
    $method->setAccessible(true);
    
    $backup = $method->invoke($adminController);
    
    if (isset($backup['backup_info']) && isset($backup['config'])) {
        echo "✓ Complete backup creation works\n";
    } else {
        echo "✗ Backup missing required fields\n";
    }
} catch (Exception $e) {
    echo "✗ Backup creation failed: " . $e->getMessage() . "\n";
}

// Test 4: AdminController backup validation
echo "\n4. Testing AdminController backup validation...\n";
try {
    $adminController = new AdminController();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($adminController);
    $method = $reflection->getMethod('validateBackupData');
    $method->setAccessible(true);
    
    $validBackup = [
        'backup_info' => ['version' => '2.0.0'],
        'config' => [
            'general' => [],
            'fields' => [],
            'images' => [],
            'cache' => [],
            'api' => [],
            'advanced' => []
        ]
    ];
    
    $validation = $method->invoke($adminController, $validBackup);
    
    if ($validation['valid'] === true) {
        echo "✓ Backup validation works for valid data\n";
    } else {
        echo "✗ Backup validation failed for valid data\n";
    }
    
    // Test invalid backup
    $invalidBackup = ['invalid' => 'data'];
    $validation = $method->invoke($adminController, $invalidBackup);
    
    if ($validation['valid'] === false && !empty($validation['errors'])) {
        echo "✓ Backup validation correctly rejects invalid data\n";
    } else {
        echo "✗ Backup validation should reject invalid data\n";
    }
} catch (Exception $e) {
    echo "✗ Backup validation failed: " . $e->getMessage() . "\n";
}

echo "\nAdvanced configuration features test completed!\n";