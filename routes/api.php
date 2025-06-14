<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketController;



Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
Route::get('/illustrations', [DashboardController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'showProfile']);
Route::get('/market/illustrations', [MarketController::class, 'getIllustrationsForMarket']);
Route::get('/illustrations/{id}', [MarketController::class, 'showIllustrationsApi']);
Route::get('/categories', [MarketController::class, 'getCategoriesApi']);
Route::middleware('auth:sanctum')->post('/illustrations', [MarketController::class, 'sell']);
Route::post('/purchase', [MarketController::class, 'buy']);
Route::get('/market/filter', [MarketController::class, 'filter']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/illustrator/listings', [DashboardController::class, 'getMyListings']);

     Route::get('/user', function (Request $request) {
        return $request->user()->load('customer', 'illustrator'); // Muat relasi jika perlu
    });

    Route::get('/collections', [DashboardController::class, 'showCollectionsApi']);
    Route::get('/histories', [DashboardController::class, 'showHistoriesApi']);
});