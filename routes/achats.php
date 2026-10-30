<?php

use App\Http\Controllers\Achats\ArticleController;
use App\Http\Controllers\Achats\MissingArticleReportController;
use App\Http\Controllers\Achats\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

// M8 — Achats. Lecture : `achats.view` ; le reste est décidé par les policies.
Route::middleware(['auth', 'verified', 'can:achats.view'])->prefix('achats')->name('achats.')->group(function () {
    // M8.1 — Demandes d'achat : saisie, validation motivée, annulation.
    Route::get('demandes', [PurchaseRequestController::class, 'index'])->name('demandes');
    Route::get('demandes/creer', [PurchaseRequestController::class, 'create'])->name('demandes.create');
    Route::post('demandes', [PurchaseRequestController::class, 'store'])->name('demandes.store');
    Route::get('demandes/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->whereNumber('purchaseRequest')->name('demandes.show');
    Route::get('demandes/{purchaseRequest}/modifier', [PurchaseRequestController::class, 'edit'])->whereNumber('purchaseRequest')->name('demandes.edit');
    Route::put('demandes/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->whereNumber('purchaseRequest')->name('demandes.update');
    Route::post('demandes/{purchaseRequest}/valider', [PurchaseRequestController::class, 'validateRequest'])->whereNumber('purchaseRequest')->name('demandes.validate');
    Route::post('demandes/{purchaseRequest}/refuser', [PurchaseRequestController::class, 'refuse'])->whereNumber('purchaseRequest')->name('demandes.refuse');
    Route::post('demandes/{purchaseRequest}/renvoyer', [PurchaseRequestController::class, 'sendBack'])->whereNumber('purchaseRequest')->name('demandes.return');
    Route::post('demandes/{purchaseRequest}/annuler', [PurchaseRequestController::class, 'cancel'])->whereNumber('purchaseRequest')->name('demandes.cancel');
    Route::get('a-valider', [PurchaseRequestController::class, 'pending'])->name('a-valider');

    // M8.3 — Référentiel des prix : écriture réservée au Service Achats BTP (policy).
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
