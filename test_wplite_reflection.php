<?php
/**
 * Test WPLite Reflection Compatibility with PHP 7.4
 */

// Test the specific reflection issue
class TestClass {
    public function __construct(string $param1, int $param2 = 10) {
        
    }
}

echo "Testing Reflection API compatibility...\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

try {
    $reflection = new ReflectionClass(TestClass::class);
    $constructor = $reflection->getConstructor();
    
    if ($constructor) {
        $dependencies = $constructor->getParameters();
        
        foreach ($dependencies as $dependency) {
            echo "Parameter: " . $dependency->getName() . "\n";
            
            // This is the problematic line from WPLite
            $type = $dependency->getType();
            
            if ($type) {
                echo "  Has type: " . ($type ? 'yes' : 'no') . "\n";
                
                // Check if isBuiltin method exists and works
                if (method_exists($type, 'isBuiltin')) {
                    echo "  Is builtin: " . ($type->isBuiltin() ? 'yes' : 'no') . "\n";
                } else {
                    echo "  ERROR: isBuiltin method not available\n";
                }
                
                // Check if getName method exists and works
                if (method_exists($type, 'getName')) {
                    echo "  Type name: " . $type->getName() . "\n";
                } else {
                    echo "  ERROR: getName method not available\n";
                }
            } else {
                echo "  No type information\n";
            }
            
            echo "  Has default: " . ($dependency->isDefaultValueAvailable() ? 'yes' : 'no') . "\n";
            if ($dependency->isDefaultValueAvailable()) {
                echo "  Default value: " . var_export($dependency->getDefaultValue(), true) . "\n";
            }
            echo "\n";
        }
    }
    
    echo "✅ Reflection API test passed!\n";
    
} catch (Throwable $e) {
    echo "❌ Reflection API test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}

// Test the WPLite Application make method specifically
echo "\nTesting WPLite Application make method...\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Mock WordPress functions
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) { return $default; }
    }
    
    $app = new \Forooshyar\WPLite\Application();
    
    // Try to make a simple class
    $configService = $app->make(\Forooshyar\Services\ConfigService::class);
    echo "✅ Successfully created ConfigService\n";
    
    // Try to make a class with dependencies
    $titleBuilder = $app->make(\Forooshyar\Services\TitleBuilder::class, [
        'configService' => $configService
    ]);
    echo "✅ Successfully created TitleBuilder with dependencies\n";
    
    echo "✅ WPLite Application make method works correctly!\n";
    
} catch (Throwable $e) {
    echo "❌ WPLite Application test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}