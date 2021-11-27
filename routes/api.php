<?php

use App\Http\Controllers\OfficeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagController;



Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class, 'index']);
Route::post('/offices', [OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::get('/offices{office}', [OfficeController::class, 'show']);
Route::post('/offices{office} ', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
