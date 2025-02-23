<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'API v1 is working tanpa reload octane 5.7',
        'status' => 'success',
        'timestamp' => now(),
        'version' => '1.0'
    ]);
});

// API Routes
Route::prefix('v1')->group(function () {
    // Products Routes
    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::apiResource('products', ProductController::class)->names('products');
    
    // Categories Routes
    Route::apiResource('categories', CategoryController::class)->names('categories');
});
