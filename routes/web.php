<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

// Dipanggil Vercel Cron tiap hari: query ringan supaya project Supabase gratis tidak di-pause karena tidak aktif.
// Di luar grup /api supaya tidak butuh X-Internal-Key (tidak membuka data apa pun).
Route::get('/up-db', function () {
    DB::select('select 1');
    return response('ok');
})->middleware('throttle:10,1');
