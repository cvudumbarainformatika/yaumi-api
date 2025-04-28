<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SatuanController;
use App\Http\Controllers\Api\SupplierController;

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

    // Satuan Routes
    Route::apiResource('satuans', SatuanController::class)->names('satuans');

    // Supplier Routes
    Route::get('suppliers/search', [SupplierController::class, 'search'])->name('suppliers.search');
    Route::apiResource('suppliers', SupplierController::class)->names('suppliers');
    // Tambahkan route untuk Customer
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    Route::apiResource('purchase-orders', \App\Http\Controllers\Api\PurchaseOrderController::class);
    Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseController::class);
});
