<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashFlowController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DistribusiGudangController;
use App\Http\Controllers\Api\KasController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\PembayaranHutangController;
use App\Http\Controllers\Api\PembayaranPiutangController;
use App\Http\Controllers\Api\PriceHistoryController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReturnPembelianController;
use App\Http\Controllers\Api\ReturnPenjualanController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SatuanController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UsersController;

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


    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/menu', [MenuController::class, 'index']);

    Route::prefix('settings')->group(function () {

        Route::prefix('company')->group(function () {
            Route::get('/', [CompanyController::class, 'show']);
            Route::put('/', [CompanyController::class, 'update']);
        });

    });


    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/logout', [AuthController::class, 'logout']);



        // Products Routes
        Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
        Route::get('products/mutations/{id}', [ProductController::class, 'mutations'])->name('products.mutations');
        Route::get('products/mutations/gudang/{id}', [ProductController::class, 'mutations_gudang'])->name('products.mutations.gudang');
        Route::post('products/stock-opname', [ProductController::class, 'stockOpname'])->name('products.stockopname.mutations');
        Route::post('products/stock-opname/gudang', [ProductController::class, 'stockOpnameGudang'])->name('products.stockopname.mutations.gudang');

        Route::apiResource('products', ProductController::class)->names('products');

        // Categories Routes
        Route::apiResource('categories', CategoryController::class)->names('categories');

        // Satuan Routes
        Route::apiResource('satuans', SatuanController::class)->names('satuans');

        // Supplier Routes
        Route::get('suppliers/search', [SupplierController::class, 'index'])->name('suppliers.search');
        Route::apiResource('suppliers', SupplierController::class)->names('suppliers');

        
        // Tambahkan route untuk Customer
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::apiResource('customers', CustomerController::class)->names('customers');

        // Tambahkan route untuk Purchase Order dan Purchase
        Route::apiResource('purchase-orders', \App\Http\Controllers\Api\PurchaseOrderController::class);
        Route::put('purchase-orders/{id}/status', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'updateStatus']);
        Route::put('purchase-orders/{id}/receive', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'receiveItems']);

        // Tambahkan route untuk Sales Order dan Sales
        Route::middleware(['prevent-duplicate'])->group(function () {
            Route::get('purchases/search', [PurchaseController::class, 'search'])->name('purchases.search');
            Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseController::class)->names('purchases');


            // Tambahkan route untuk sales
            Route::get('sales/search', [SalesController::class, 'search'])->name('sales.search');
            Route::apiResource('sales', SalesController::class)->names('sales');
        });


        // Route::apiResource('sales', \App\Http\Controllers\Api\SalesController::class)->names('sales');


        Route::prefix('return-penjualan')->controller(ReturnPenjualanController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            // Route::get('{id}', 'show');
        });


        Route::prefix('return-pembelian')->controller(ReturnPembelianController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('{id}', 'show');
        });

        Route::prefix('pembayaran-hutang')->controller(PembayaranHutangController::class)->group(function () {
            Route::get('/search', 'search');
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('{id}', 'show');
        });

        Route::prefix('pembayaran-piutang')->controller(PembayaranPiutangController::class)->group(function () {
            Route::get('/search', 'search');
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('{id}', 'show');
        });

        Route::prefix('kas')->group(function () {
            Route::get('/', [KasController::class, 'index']);
            Route::post('/', [KasController::class, 'store']);
            Route::get('{id}', [KasController::class, 'show']);
            Route::put('{id}', [KasController::class, 'update']);
            Route::delete('{id}', [KasController::class, 'destroy']);
        });
        Route::prefix('users')->group(function () {
            Route::get('/list', [UsersController::class, 'list']);
            Route::get('/', [UsersController::class, 'index']);
            Route::post('/', [UsersController::class, 'store']);
            Route::get('{id}', [UsersController::class, 'show']);
            Route::put('{id}', [UsersController::class, 'update']);
            Route::delete('{id}', [UsersController::class, 'destroy']);
        });


        Route::prefix('cash-flows')->group(function () {
            Route::get('/', [CashFlowController::class, 'index']);
            Route::post('/', [CashFlowController::class, 'store']);
            Route::get('{id}', [CashFlowController::class, 'show']);
        });


        Route::prefix('reports')->group(function () {
            // penjualan
            Route::get('/sales', [SalesController::class, 'report']);
            Route::get('/sales/summary', [SalesController::class, 'rekap']);
            // pembelian
            Route::get('/pembelian', [PurchaseController::class, 'report']);
            Route::get('/pembelian/summary', [PurchaseController::class, 'rekap']);
            // return penjualan
            Route::get('/return-penjualan', [ReturnPenjualanController::class, 'report']);
            Route::get('/return-penjualan/summary', [ReturnPenjualanController::class, 'rekap']);
            // return penjualan
            Route::get('/return-pembelian', [ReturnPembelianController::class, 'report']);
            Route::get('/return-penjualan/summary', [ReturnPenjualanController::class, 'rekap']);

            // laba rugi
            Route::get('/labarugi', [LaporanController::class, 'labarugi']);

            // rekapkasir
            Route::get('/rekapkasir', [LaporanController::class, 'cashFlows']);

            //stock-opname
            Route::get('/stock-opname', [StockOpnameController::class, 'index']);
            Route::get('/stock-opname-gudang', [StockOpnameController::class, 'gudang']);


            Route::get('/perubahan-harga-beli', [PriceHistoryController::class, 'index']);
        });


        Route::prefix('settings')->group(function () {
            // penjualan
            
            Route::get('/menu', [MenuController::class, 'index']);
            Route::post('/menu/permissions', [MenuController::class, 'updateBulkPermissions']);

        });
        Route::prefix('gudangs')->group(function () {

            Route::prefix('distribution')->controller(DistribusiGudangController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('{id}', 'show');
            });

        });


        


        Route::prefix('dashboard')->group(function () {
            // penjualan
            
            Route::get('/penjualan', [DashboardController::class, 'penjualan']);
            Route::get('/cart-penjualan', [DashboardController::class, 'cartPenjualan']);
            Route::get('/top-product-sales', [DashboardController::class, 'topProductSales']);


            Route::get('/pembelian', [DashboardController::class, 'pembelian']);
            Route::get('/cart-pembelian', [DashboardController::class, 'cartPembelian']);
            Route::get('/activity', [DashboardController::class, 'activity']);

            Route::get('/metrics/sales-daily', [MetricsController::class, 'salesDaily']);
            Route::get('/metrics/purchases-weekly', [MetricsController::class, 'purchasesWeekly']);
            


        });

        


    });



    
});

// Route::prefix('v1')->middleware(['prevent-duplicate'])->group(function () {
//     Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseController::class);
//     Route::post('/sales', [SalesController::class, 'store']);
//     // ... route lainnya yang mengubah stok
// });
