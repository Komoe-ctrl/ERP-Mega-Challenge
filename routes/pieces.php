<?php

use App\Http\Controllers\Pieces\PieceController;
use Illuminate\Support\Facades\Route;

// Pièces communes (note de frais, recette, pièce de caisse) : saisie, circuit de validation, panneau budget.
// Les droits viennent de la PiecePolicy (module du type de pièce + rôle de l'étape du circuit).
Route::middleware(['auth', 'verified'])->prefix('pieces/{kind}')->name('pieces.')->group(function () {
    Route::get('/', [PieceController::class, 'index'])->name('index');
    Route::get('budget', [PieceController::class, 'budget'])->name('budget');
    Route::post('/', [PieceController::class, 'store'])->name('store');
    Route::get('{piece}', [PieceController::class, 'show'])->whereNumber('piece')->name('show');
    Route::put('{piece}', [PieceController::class, 'update'])->whereNumber('piece')->name('update');
    Route::delete('{piece}', [PieceController::class, 'destroy'])->whereNumber('piece')->name('destroy');
    Route::post('{piece}/soumettre', [PieceController::class, 'submit'])->whereNumber('piece')->name('submit');
    Route::post('{piece}/valider', [PieceController::class, 'advance'])->whereNumber('piece')->name('advance');
    Route::post('{piece}/rejeter', [PieceController::class, 'reject'])->whereNumber('piece')->name('reject');
});
