<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/orders/current', [OrderController::class, 'current']);
Route::get('/orders/{order}',  [OrderController::class, 'show']);
Route::get('/orders',          [OrderController::class, 'index']);
