<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChargerController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\PointsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::get('/chargers', [ChargerController::class, 'index'])->name('api.chargers.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');

    Route::prefix('chargers')->name('api.chargers.')->group(function () {
        Route::post('/{charger}/schedule', [ChargerController::class, 'schedule'])->name('schedule');
        Route::post('/{charger}/start', [ChargerController::class, 'start'])->name('start');
        Route::post('/{charger}/release', [ChargerController::class, 'release'])->name('release');
    });

    Route::prefix('wallet')->name('api.wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::get('/sessions', [WalletController::class, 'sessions'])->name('sessions');
        Route::post('/deposit', [WalletController::class, 'deposit'])->name('deposit');
        Route::get('/points', [PointsController::class, 'index'])->name('points');
        Route::post('/redeem-points', [PointsController::class, 'redeem'])->name('redeem-points');
    });
});
