<?php

use App\Http\Controllers\ChargingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('chargers.index'));

Route::get('/login-user', [UserController::class, 'login'])->name('user.login');
Route::post('/login', [UserController::class, 'logar'])->name('login-user');

Route::get('/recover-password', [UserController::class, 'showPasswordRecovery'])->name('user.password.recovery');
Route::post('/recover-password/verify', [UserController::class, 'verifyPasswordRecovery'])->name('user.password.verify');
Route::post('/recover-password/update', [UserController::class, 'updatePassword'])->name('user.password.update');

Route::get('/create-user', [UserController::class, 'create'])->name('user.cadastro');
Route::post('/store-user', [UserController::class, 'store'])->name('user-store');

Route::post('/logout', [UserController::class, 'logout'])->name('logout');

Route::get('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['pt', 'en', 'es'])) {
        abort(400);
    }

    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.set');

Route::middleware('auth')->group(function () {
    Route::prefix('carregadores')->name('chargers.')->group(function () {
        Route::get('/', [ChargingController::class, 'index'])->name('index');
        Route::post('/{charger}/agendar', [ChargingController::class, 'schedule'])->name('schedule');
        Route::post('/{charger}/liberar', [ChargingController::class, 'release'])->name('release');
    });
});
