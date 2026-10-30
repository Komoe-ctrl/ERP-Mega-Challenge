<?php

use App\Http\Controllers\Achats\ArticleController;
use App\Http\Controllers\Achats\MissingArticleReportController;
use Illuminate\Support\Facades\Route;

// M8.3 — Référentiel des prix. Lecture : `achats.view` ; écriture : policy (Service Achats BTP seul).
Route::middleware(['auth', 'verified', 'can:achats.view'])->prefix('achats')->name('achats.')->group(function () {
    Route::get('referentiel', [ArticleController::class, 'index'])->name('referentiel');
    Route::get('referentiel/creer', [ArticleController::class, 'create'])->name('referentiel.create');
    Route::post('referentiel', [ArticleController::class, 'store'])->name('referentiel.store');
    Route::post('referentiel/import', [ArticleController::class, 'import'])->name('referentiel.import');
    Route::get('referentiel/{article}', [ArticleController::class, 'edit'])->whereNumber('article')->name('referentiel.edit');
    Route::put('referentiel/{article}', [ArticleController::class, 'update'])->whereNumber('article')->name('referentiel.update');
    Route::patch('referentiel/{article}/desactiver', [ArticleController::class, 'deactivate'])->whereNumber('article')->name('referentiel.deactivate');
    Route::patch('referentiel/{article}/reactiver', [ArticleController::class, 'reactivate'])->whereNumber('article')->name('referentiel.reactivate');

    Route::post('referentiel/signalements', [MissingArticleReportController::class, 'store'])->name('referentiel.signalements.store');
    Route::patch('referentiel/signalements/{report}/traiter', [MissingArticleReportController::class, 'resolve'])->whereNumber('report')->name('referentiel.signalements.resolve');
});
