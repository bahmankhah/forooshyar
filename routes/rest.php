<?php

use Forooshyar\Controllers\ProductController;
use Forooshyar\Middleware\ApiLoggingMiddleware;
use Forooshyar\WPLite\Facades\Route;

Route::rest(function ($router) {
    // Main products endpoint - GET /products
    $router->get('/products', [ProductController::class, 'index'])->middleware(ApiLoggingMiddleware::class)->make();

    // legacy routes
    $router->get('/torob/products', [ProductController::class, 'index'])->middleware(ApiLoggingMiddleware::class)->make();
    $router->get('/emalls/products', [ProductController::class, 'index'])->middleware(ApiLoggingMiddleware::class)->make();
    
    // Single product endpoint - GET /products/{id}
    $router->get('/products/{id}', [ProductController::class, 'show'])->middleware(ApiLoggingMiddleware::class)->make();
    
    // Products by IDs - POST /products/by-ids
    $router->post('/products/by-ids', [ProductController::class, 'getByIds'])->middleware(ApiLoggingMiddleware::class)->make();
    
    // Products by slugs - POST /products/by-slugs
    $router->post('/products/by-slugs', [ProductController::class, 'getBySlugs'])->middleware(ApiLoggingMiddleware::class)->make();
});