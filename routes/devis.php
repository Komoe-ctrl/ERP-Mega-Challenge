<?php

use App\Http\Controllers\Devis\QuoteController;
use Illuminate\Support\Facades\Route;

// M5 — Devis : brouillon, ouvrages, soumission, validation, refus, versions. Droits : QuotePolicy (`devis.*`).
Route::middleware(['auth', 'verified', 'can:devis.view'])->prefix('devis')->name('devis.')->group(function () {
    Route::get('/', [QuoteController::class, 'index'])->name('index');
    Route::post('/', [QuoteController::class, 'store'])->name('store');
    Route::get('{quote}', [QuoteController::class, 'show'])->whereNumber('quote')->name('show');
    Route::put('{quote}', [QuoteController::class, 'update'])->whereNumber('quote')->name('update');
    Route::post('{quote}/ouvrages', [QuoteController::class, 'addOuvrage'])->whereNumber('quote')->name('ouvrages.store');
    Route::post('{quote}/soumettre', [QuoteController::class, 'submit'])->whereNumber('quote')->name('submit');
    Route::post('{quote}/valider', [QuoteController::class, 'validateQuote'])->whereNumber('quote')->name('validate');
    Route::post('{quote}/refuser', [QuoteController::class, 'refuse'])->whereNumber('quote')->name('refuse');
    Route::post('{quote}/version', [QuoteController::class, 'newVersion'])->whereNumber('quote')->name('version');
});
