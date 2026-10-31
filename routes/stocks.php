<?php

use App\Http\Controllers\Stocks\InventoryController;
use App\Http\Controllers\Stocks\MovementController;
use App\Http\Controllers\Stocks\StockController;
use Illuminate\Support\Facades\Route;

// M9 — Stocks BTP : état valorisé, mouvements, inventaires. Les droits viennent de la StockPolicy
// (lecture `stocks.view`, saisie `stocks.create`, validation `stocks.validate`).
Route::middleware(['auth', 'verified', 'can:stocks.view'])->prefix('stocks')->name('stocks.')->group(function () {
    Route::get('/', [StockController::class, 'index'])->name('index');
    Route::put('niveaux/{level}/seuil', [StockController::class, 'setMinimum'])->whereNumber('level')->name('minimum');

    Route::get('mouvements', [MovementController::class, 'index'])->name('mouvements');
    Route::post('mouvements', [MovementController::class, 'store'])->name('mouvements.store');
    Route::post('mouvements/{movement}/valider', [MovementController::class, 'validateMovement'])->whereNumber('movement')->name('mouvements.validate');
    Route::post('mouvements/{movement}/rejeter', [MovementController::class, 'reject'])->whereNumber('movement')->name('mouvements.reject');

    Route::get('inventaire', [InventoryController::class, 'index'])->name('inventaire');
    Route::post('inventaire', [InventoryController::class, 'store'])->name('inventaire.store');
    Route::put('inventaire/{inventory}', [InventoryController::class, 'count'])->whereNumber('inventory')->name('inventaire.count');
    Route::post('inventaire/{inventory}/valider', [InventoryController::class, 'validateInventory'])->whereNumber('inventory')->name('inventaire.validate');
});
