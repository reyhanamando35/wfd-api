<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
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

Route::middleware(['auth:sanctum', 'user'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/check-email', [AdminController::class, 'checkEmail']);
    Route::post('/logout', [AdminController::class, 'logout']);
});
