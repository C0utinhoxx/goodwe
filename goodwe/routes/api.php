<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChargerController;
use App\Http\Controllers\Api\ChargingSessionController;
use App\Http\Controllers\Api\FirestoreUserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\ChargingSessionController as WebChargingSessionController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('api.login');

Route::get('/chargers', [ChargerController::class, 'index'])->name('api.chargers.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
});

Route::middleware(['auth:sanctum', 'portal.user'])->group(function () {
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');

    Route::post('/chargers/{charger:code}/reserve', [ReservationController::class, 'store'])->name('api.reservations.store');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('api.reservations.cancel');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'status'])->name('api.reservations.status');
    Route::post('/sessions/{session}/pay', [ChargingSessionController::class, 'pay'])->name('api.sessions.pay');

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

    Route::prefix('firestore')->name('api.firestore.')->group(function () {
        Route::post('/users', [FirestoreUserController::class, 'store'])->name('users.store');
        Route::put('/users/{tagUid}', [FirestoreUserController::class, 'update'])->name('users.update');
        Route::post('/users/{tagUid}/comando', [FirestoreUserController::class, 'setCommand'])->name('users.command');
        Route::get('/charging-sessions', [WebChargingSessionController::class, 'index'])->name('charging-sessions.index');
    });
});
