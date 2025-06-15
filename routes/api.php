<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketController;



Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
Route::get('/illustrations/dashboard', [DashboardController::class, 'dashboardList']);
Route::get('/users/{id}', [DashboardController::class, 'showProfile']);
Route::get('/market/illustrations', [MarketController::class, 'getIllustrationsForMarket']);
Route::get('/illustrations/{id}', [MarketController::class, 'showIllustrationsApi']);
Route::get('/categories', [MarketController::class, 'getCategoriesApi']);
Route::middleware('auth:sanctum')->post('/illustrations', [MarketController::class, 'sell']);
// Route::post('/purchase', [MarketController::class, 'buy']);
Route::get('/market/filter', [MarketController::class, 'filter']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/illustrator/listings', [DashboardController::class, 'getMyListings']);
    Route::post('/purchase', [MarketController::class, 'buy']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('customer', 'illustrator'); // Muat relasi jika perlu
    });

    Route::get('/collections', [DashboardController::class, 'showCollectionsApi']);
    Route::get('/histories', [DashboardController::class, 'showHistoriesApi']);
});
Route::post('/register/illustrator', [AuthController::class, 'registerIllustrator']);
Route::post('/login/customer', [AuthController::class, 'loginCustomer']);
Route::post('/login/illustrator', [AuthController::class, 'loginIllustrator']);
Route::get('/customers', [AdminController::class, 'showCustomers']);
Route::get('/illustrators', [AdminController::class, 'showIllustrators']);
Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
Route::get('/customers/{id}', [AdminController::class, 'showEditCustomer']);
Route::get('/illustrators/{id}', [AdminController::class, 'showEditIllustrator']);
Route::put('/editCustomer/{id}', [AdminController::class, 'editCustomer']);
Route::put('/editIllustrator/{id}', [AdminController::class, 'editIllustrator']);
Route::get('/purchases', [AdminController::class, 'showPurchases']);
Route::post('/purchases/{id}/verify', [AdminController::class, 'verify']);
Route::post('/purchases/{id}/reject', [AdminController::class, 'reject']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);


// Route::middleware(['auth:sanctum', 'user'])->group(function () {
//     Route::post('/logout', [AuthController::class, 'logout']);
// });
Route::post('/admin/check-email', [AdminController::class, 'checkEmail']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Route::post('/admin/check-email', [AdminController::class, 'checkEmail']);
    Route::post('/admin/logout', [AdminController::class, 'logout']);
});
