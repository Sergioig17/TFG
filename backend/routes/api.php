<?php

use Illuminate\Support\Facades\Route;

Route::post('/procesar', [\App\Http\Controllers\AudioController::class, 'procesar']);

