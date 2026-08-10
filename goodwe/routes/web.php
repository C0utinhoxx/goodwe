<?php

use App\Http\Controllers\ChargingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('chargers.index'));

Route::prefix('carregadores')->name('chargers.')->group(function () {
    Route::get('/', [ChargingController::class, 'index'])->name('index');
    Route::post('/{charger}/agendar', [ChargingController::class, 'schedule'])->name('schedule');
    Route::post('/{charger}/liberar', [ChargingController::class, 'release'])->name('release');
});
