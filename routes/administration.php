<?php

use App\Http\Controllers\Administration\ActivityController;
use App\Http\Controllers\Administration\MatrixController;
use App\Http\Controllers\Administration\OrganizationController;
use App\Http\Controllers\Administration\RoleController;
use App\Http\Controllers\Administration\SettingController;
use App\Http\Controllers\Administration\UserController;
use Illuminate\Support\Facades\Route;

// CA1 : sans le droit `administration.view`, le serveur refuse la requête
// (403), quel que soit ce que l'interface affiche.
Route::middleware(['auth', 'verified', 'can:administration.view'])
    ->prefix('administration')
    ->name('administration.')
    ->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

        Route::resource('roles', RoleController::class)->except(['show', 'destroy']);

        Route::get('matrice', MatrixController::class)->name('matrice');

        // Organisation : agences, services, sites (désactivation motivée, jamais de suppression).
        Route::get('organisation', [OrganizationController::class, 'index'])->name('organisation');
        Route::post('agencies', [OrganizationController::class, 'storeAgency'])->name('agencies.store');
        Route::patch('agencies/{agency}/deactivate', [OrganizationController::class, 'deactivateAgency'])->name('agencies.deactivate');
        Route::patch('agencies/{agency}/reactivate', [OrganizationController::class, 'reactivateAgency'])->name('agencies.reactivate');
        Route::post('services', [OrganizationController::class, 'storeService'])->name('services.store');
        Route::patch('services/{service}/deactivate', [OrganizationController::class, 'deactivateService'])->name('services.deactivate');
        Route::patch('services/{service}/reactivate', [OrganizationController::class, 'reactivateService'])->name('services.reactivate');
        Route::post('sites', [OrganizationController::class, 'storeSite'])->name('sites.store');
        Route::patch('sites/{site}/deactivate', [OrganizationController::class, 'deactivateSite'])->name('sites.deactivate');
        Route::patch('sites/{site}/reactivate', [OrganizationController::class, 'reactivateSite'])->name('sites.reactivate');

        // Paramètres (RG1.3) et exercices comptables (RG1.4).
        Route::get('parametres', [SettingController::class, 'index'])->name('parametres');
        Route::put('parametres', [SettingController::class, 'update'])->name('parametres.update');
        Route::patch('fiscal-years/{fiscalYear}/close', [SettingController::class, 'closeFiscalYear'])->name('fiscal-years.close');

        // Journal des activités, en lecture seule (RG1.5).
        Route::get('journal', [ActivityController::class, 'index'])->name('journal');
    });
