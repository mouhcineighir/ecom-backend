<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController as PublicCategoryController;

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;

use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;

use App\Http\Controllers\Api\Admin\ProductImageController;

use App\Http\Controllers\Api\OrderController;

use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;

use App\Http\Controllers\Api\Admin\DashboardController;

Route::prefix('v1')->group(function () {

    // =========================
    // PUBLIC API
    // =========================

    // Public categories
    Route::get('/categories', [PublicCategoryController::class, 'index']);
    Route::get('/categories/{slug}', [PublicCategoryController::class, 'show']);

    // Public products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Public orders
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders');
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});


Route::prefix('v1/admin')->group(function () {

    // =========================
    // ADMIN AUTHENTICATION
    // =========================

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');


    // =========================
    // PROTECTED ADMIN API
    // =========================

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {

        // Authentication
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);


        // Category CRUD
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::get('/categories/{id}', [AdminCategoryController::class, 'show']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

        // Product CRUD
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::get('/products/{id}', [AdminProductController::class, 'show']);
        Route::put('/products/{id}', [AdminProductController::class, 'update']);
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

        Route::post('/products/{product}/images', [ProductImageController::class, 'store']);
        Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy']);

        // Order management
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::put('/orders/{id}/notes', [AdminOrderController::class, 'updateNotes']);


        // Dashboard 
        Route::get('/dashboard', [DashboardController::class, 'index']);
        
    });
});