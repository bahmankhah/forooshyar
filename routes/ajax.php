<?php

use Forooshyar\Controllers\AdminController;
use Forooshyar\Controllers\AIAgentController;
use Forooshyar\WPLite\Facades\Route;

Route::ajax(function ($router) {
    // ============================================
    // Forooshyar Core Settings
    // ============================================
    
    // Settings management
    $router->post('forooshyar_save_settings', [AdminController::class, 'saveSettings'])->make();
    $router->post('forooshyar_reset_settings', [AdminController::class, 'resetSettings'])->make();
    
    // API testing and monitoring
    $router->post('forooshyar_test_api', [AdminController::class, 'testApi'])->make();
    $router->get('forooshyar_get_logs', [AdminController::class, 'getLogs'])->make();
    $router->get('forooshyar_get_stats', [AdminController::class, 'getStatsAjax'])->make();
    
    // API logging and monitoring
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
    
    // Cache management
    $router->post('forooshyar_cache_action', [AdminController::class, 'cacheAction'])->make();
    $router->get('forooshyar_get_cache_stats', [AdminController::class, 'getCacheStats'])->make();
    
    // Settings export/import
    $router->get('forooshyar_export_settings', [AdminController::class, 'exportSettings'])->make();
    $router->post('forooshyar_import_settings', [AdminController::class, 'importSettings'])->make();
    
    // Advanced operations
    $router->post('forooshyar_performance_test', [AdminController::class, 'performanceTest'])->make();
    $router->post('forooshyar_cleanup', [AdminController::class, 'cleanup'])->make();
    $router->get('forooshyar_export_all_settings', [AdminController::class, 'exportAllSettings'])->make();
    $router->post('forooshyar_import_all_settings', [AdminController::class, 'importAllSettings'])->make();
    
    // ============================================
    // AI Agent Module
    // ============================================
    
    // Analysis job management
    $router->post('aiagent_start_analysis', [AIAgentController::class, 'startAnalysis'])->make();
    $router->post('aiagent_cancel_analysis', [AIAgentController::class, 'cancelAnalysis'])->make();
    $router->post('aiagent_get_analysis_progress', [AIAgentController::class, 'getAnalysisProgress'])->make();
    $router->post('aiagent_process_batch', [AIAgentController::class, 'processBatch'])->make();
    $router->post('aiagent_acknowledge_completion', [AIAgentController::class, 'acknowledgeCompletion'])->make();
    $router->post('aiagent_run_analysis', [AIAgentController::class, 'runAnalysis'])->make(); // Legacy
    
    // Action management
    $router->post('aiagent_execute_action', [AIAgentController::class, 'executeAction'])->make();
    $router->post('aiagent_approve_action', [AIAgentController::class, 'approveAction'])->make();
    $router->post('aiagent_dismiss_action', [AIAgentController::class, 'dismissAction'])->make();
    $router->post('aiagent_approve_all_actions', [AIAgentController::class, 'approveAllActions'])->make();
    $router->post('aiagent_dismiss_all_actions', [AIAgentController::class, 'dismissAllActions'])->make();
    
    // Statistics and testing
    $router->post('aiagent_get_stats', [AIAgentController::class, 'getStats'])->make();
    $router->post('aiagent_test_connection', [AIAgentController::class, 'testConnection'])->make();
    
    // AI Agent settings
    $router->post('aiagent_save_settings', [AIAgentController::class, 'saveSettings'])->make();
    $router->post('aiagent_reset_settings', [AIAgentController::class, 'resetSettings'])->make();
    $router->post('aiagent_export_settings', [AIAgentController::class, 'exportSettings'])->make();
    $router->post('aiagent_import_settings', [AIAgentController::class, 'importSettings'])->make();
});
