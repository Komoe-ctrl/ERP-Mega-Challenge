<?php

use App\Http\Controllers\Budget\BudgetController;
use Illuminate\Support\Facades\Route;

// M10 — Budget et dépenses d'un chantier. Lecture : `budget.view` ; écriture : policy.
Route::middleware(['auth', 'verified', 'can:budget.view'])->prefix('budget')->name('budget.')->group(function () {
    Route::get('/', [BudgetController::class, 'index'])->name('index');
    Route::get('{chantier}', [BudgetController::class, 'show'])->whereNumber('chantier')->name('show');
    Route::post('{chantier}/postes', [BudgetController::class, 'storeLine'])->whereNumber('chantier')->name('lines.store');
    Route::post('{chantier}/revisions', [BudgetController::class, 'requestRevision'])->whereNumber('chantier')->name('revisions.store');
    Route::post('{chantier}/depenses', [BudgetController::class, 'storeExpense'])->whereNumber('chantier')->name('expenses.store');
    Route::post('revisions/{revision}/valider', [BudgetController::class, 'validateRevision'])->whereNumber('revision')->name('revisions.validate');
    Route::post('revisions/{revision}/rejeter', [BudgetController::class, 'rejectRevision'])->whereNumber('revision')->name('revisions.reject');
});
