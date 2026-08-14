<?php

use App\Http\Controllers\ChargingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/', fn () => redirect()->route('chargers.index'));

Route::get('/login-user', [UserController::class, 'login'])->name('user.login');
Route::post('/login', [UserController::class, 'logar'])->name('login-user');

Route::get('/create-user', [UserController::class, 'create'])->name('user.cadastro');
Route::post('/store-user', [UserController::class, 'store'])->name('user-store');


Route::prefix('carregadores')->name('chargers.')->group(function () {
    Route::get('/', [ChargingController::class, 'index'])->name('index');
    Route::post('/{charger}/agendar', [ChargingController::class, 'schedule'])->name('schedule');
    Route::post('/{charger}/liberar', [ChargingController::class, 'release'])->name('release');
});
