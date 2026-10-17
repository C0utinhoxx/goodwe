<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChargerController;
use App\Http\Controllers\Api\ChargingSessionController;
use App\Http\Controllers\Api\FirestoreUserController;
use App\Http\Controllers\Api\WalletController;
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
        Route::post('/redeem-points', [WalletController::class, 'redeemPoints'])->name('redeem-points');
    });

    Route::prefix('firestore')->name('api.firestore.')->group(function () {
        Route::post('/users', [FirestoreUserController::class, 'store'])->name('users.store');
        Route::put('/users/{tagUid}', [FirestoreUserController::class, 'update'])->name('users.update');
        Route::post('/users/{tagUid}/comando', [FirestoreUserController::class, 'setCommand'])->name('users.command');
        Route::get('/charging-sessions', [ChargingSessionController::class, 'index'])->name('charging-sessions.index');
    });
});
