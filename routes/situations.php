<?php

use App\Http\Controllers\Situations\SituationController;
use Illuminate\Support\Facades\Route;

// M11 — Situations de travaux : brouillon, validation qui fige, paiements. Droits : SituationPolicy (`situations.*`).
Route::middleware(['auth', 'verified', 'can:situations.view'])->prefix('situations')->name('situations.')->group(function () {
    Route::get('/', [SituationController::class, 'index'])->name('index');
    Route::post('/', [SituationController::class, 'store'])->name('store');
    Route::get('{situation}', [SituationController::class, 'show'])->whereNumber('situation')->name('show');
    Route::put('{situation}', [SituationController::class, 'update'])->whereNumber('situation')->name('update');
    Route::post('{situation}/valider', [SituationController::class, 'validateSituation'])->whereNumber('situation')->name('validate');
    Route::post('{situation}/paiements', [SituationController::class, 'storePayment'])->whereNumber('situation')->name('payments.store');
});
