<?php

use App\Http\Controllers\OfficeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagController;


Route::get('/tags', [TagController::class]);


Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class, 'index']);
