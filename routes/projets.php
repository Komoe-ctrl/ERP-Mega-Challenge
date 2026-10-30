<?php

use App\Http\Controllers\Projets\ProjectController;
use Illuminate\Support\Facades\Route;

// M4 — Projets. Les droits sont vérifiés par `can:projets.view` puis par la policy.
Route::middleware(['auth', 'verified', 'can:projets.view'])->prefix('projets')->name('projets.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('creer', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('show');
    Route::get('{project}/modifier', [ProjectController::class, 'edit'])->whereNumber('project')->name('edit');
    Route::put('{project}', [ProjectController::class, 'update'])->whereNumber('project')->name('update');
    Route::post('{project}/statut', [ProjectController::class, 'advance'])->whereNumber('project')->name('advance');
    Route::post('{project}/abandon', [ProjectController::class, 'abandon'])->whereNumber('project')->name('abandon');
    Route::post('{project}/documents', [ProjectController::class, 'storeDocument'])->whereNumber('project')->name('documents.store');
});
