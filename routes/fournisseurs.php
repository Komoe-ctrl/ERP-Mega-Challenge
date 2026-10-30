<?php

use App\Http\Controllers\Fournisseurs\SupplierController;
use Illuminate\Support\Facades\Route;

// M3 — Fournisseurs. Lecture : `fournisseurs.view` ; écriture : policy.
Route::middleware(['auth', 'verified', 'can:fournisseurs.view'])->prefix('fournisseurs')->name('fournisseurs.')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::post('/', [SupplierController::class, 'store'])->name('store');
    Route::get('{supplier}', [SupplierController::class, 'show'])->whereNumber('supplier')->name('show');
    Route::put('{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('update');
    Route::patch('{supplier}/desactiver', [SupplierController::class, 'deactivate'])->whereNumber('supplier')->name('deactivate');
    Route::patch('{supplier}/reactiver', [SupplierController::class, 'reactivate'])->whereNumber('supplier')->name('reactivate');
    Route::post('{supplier}/tarifs', [SupplierController::class, 'setTariff'])->whereNumber('supplier')->name('tariffs.store');
    Route::post('{supplier}/evaluations', [SupplierController::class, 'evaluate'])->whereNumber('supplier')->name('evaluations.store');
});
