<?php

use App\Http\Controllers\Marches\ContractController;
use Illuminate\Support\Facades\Route;

// M6 — Marchés : création depuis un devis validé, avenants. Droits : ContractPolicy (`marches.*`).
Route::middleware(['auth', 'verified', 'can:marches.view'])->prefix('marches')->name('marches.')->group(function () {
    Route::get('/', [ContractController::class, 'index'])->name('index');
    Route::post('/', [ContractController::class, 'store'])->name('store');
    Route::get('{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('show');
    Route::post('{contract}/avenants', [ContractController::class, 'storeAmendment'])->whereNumber('contract')->name('amendments.store');
    Route::post('{contract}/avenants/{amendment}/valider', [ContractController::class, 'validateAmendment'])->whereNumber('contract')->whereNumber('amendment')->name('amendments.validate');
});
