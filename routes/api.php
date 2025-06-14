<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
Route::post('/register/illustrator', [AuthController::class, 'registerIllustrator']);

