<?php
use App\Http\Controllers\AudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/procesar', [AudioController::class, 'procesar'])->withoutMiddleware('web');