<?php

use Forooshyar\Controllers\AdminController;
use WPLite\Facades\Route;

Route::admin(function ($router) {
    // Settings page
    $router->get('forooshyar-settings', [AdminController::class, 'settingsPage'])->make();
    
    // API Monitor page
    $router->get('forooshyar-monitor', [AdminController::class, 'monitorPage'])->make();
});