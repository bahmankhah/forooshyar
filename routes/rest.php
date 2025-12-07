<?php

use Forooshyar\Controllers\ProductController;
use WPLite\Facades\Route;

Route::rest(function ($router) {
    // Main products endpoint - GET /products
    $router->get('/products', [ProductController::class, 'index'])->make();
    
    // Single product endpoint - GET /products/{id}
    $router->get('/products/{id}', [ProductController::class, 'show'])->make();
    
    // Products by IDs - POST /products/by-ids
    $router->post('/products/by-ids', [ProductController::class, 'getByIds'])->make();
    
    // Products by slugs - POST /products/by-slugs
    $router->post('/products/by-slugs', [ProductController::class, 'getBySlugs'])->make();
});