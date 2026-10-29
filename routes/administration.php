<?php

use App\Http\Controllers\Administration\RoleController;
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
    });
