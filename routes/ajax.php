<?php

use Forooshyar\Controllers\AdminController;
use WPLite\Facades\Route;

Route::ajax(function ($router) {
    // Settings management
    $router->post('forooshyar_save_settings', [AdminController::class, 'saveSettings'])->make();
    $router->post('forooshyar_reset_settings', [AdminController::class, 'resetSettings'])->make();
    
    // API testing and monitoring
    $router->post('forooshyar_test_api', [AdminController::class, 'testApi'])->make();
    $router->get('forooshyar_get_logs', [AdminController::class, 'getLogs'])->make();
    $router->get('forooshyar_get_stats', [AdminController::class, 'getStatsAjax'])->make();
    
    // API logging and monitoring (new endpoints)
    $router->get('forooshyar_get_analytics', [AdminController::class, 'getAnalytics'])->make();
    $router->get('forooshyar_get_api_logs', [AdminController::class, 'getApiLogs'])->make();
    $router->get('forooshyar_get_performance_metrics', [AdminController::class, 'getPerformanceMetrics'])->make();
    $router->post('forooshyar_cleanup_logs', [AdminController::class, 'cleanupLogs'])->make();
    $router->get('forooshyar_check_rate_limit', [AdminController::class, 'checkRateLimit'])->make();
    
    // Error handling and recovery monitoring
    $router->get('forooshyar_get_error_logs', [AdminController::class, 'getErrorLogs'])->make();
    $router->get('forooshyar_get_error_stats', [AdminController::class, 'getErrorStats'])->make();
    $router->post('forooshyar_clear_error_logs', [AdminController::class, 'clearErrorLogs'])->make();
    $router->get('forooshyar_get_circuit_breaker_stats', [AdminController::class, 'getCircuitBreakerStats'])->make();
    $router->post('forooshyar_reset_circuit_breaker', [AdminController::class, 'resetCircuitBreaker'])->make();
    $router->get('forooshyar_export_error_logs', [AdminController::class, 'exportErrorLogs'])->make();
    
    // Template validation
    $router->post('forooshyar_validate_template', [AdminController::class, 'validateTemplate'])->make();
    
    // Cache management (will be implemented in later tasks)
    // $router->post('forooshyar_cache_action', [AdminController::class, 'cacheAction'])->make();
    // $router->get('forooshyar_get_cache_stats', [AdminController::class, 'getCacheStats'])->make();
    
    // Settings export/import
    $router->get('forooshyar_export_settings', [AdminController::class, 'exportSettings'])->make();
    $router->post('forooshyar_import_settings', [AdminController::class, 'importSettings'])->make();
    
    // Advanced operations
    $router->post('forooshyar_performance_test', [AdminController::class, 'performanceTest'])->make();
    $router->post('forooshyar_cleanup', [AdminController::class, 'cleanup'])->make();
    $router->get('forooshyar_export_all_settings', [AdminController::class, 'exportAllSettings'])->make();
    $router->post('forooshyar_import_all_settings', [AdminController::class, 'importAllSettings'])->make();
});