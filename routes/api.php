<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SalesController;
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
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);

    // Tambahkan route untuk Purchase Order dan Purchase
    Route::apiResource('purchase-orders', \App\Http\Controllers\Api\PurchaseOrderController::class);
    Route::put('purchase-orders/{id}/status', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'updateStatus']);
    Route::put('purchase-orders/{id}/receive', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'receiveItems']);

    // Tambahkan route untuk Sales Order dan Sales
    Route::middleware(['prevent-duplicate'])->group(function () {
        Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseController::class);
        Route::post('/sales', [SalesController::class, 'store']);
    });

    // Tambahkan route untuk sales
    Route::apiResource('sales', \App\Http\Controllers\Api\SalesController::class);
});

// Route::prefix('v1')->middleware(['prevent-duplicate'])->group(function () {
//     Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseController::class);
//     Route::post('/sales', [SalesController::class, 'store']);
//     // ... route lainnya yang mengubah stok
// });
