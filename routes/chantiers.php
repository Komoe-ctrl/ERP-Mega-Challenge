<?php

use App\Http\Controllers\Chantiers\ChantierController;
use App\Http\Controllers\Chantiers\DailyReportController;
use App\Http\Controllers\Chantiers\PlanningController;
use App\Http\Controllers\Chantiers\ProgressController;
use Illuminate\Support\Facades\Route;

// M7 — Chantiers. `can:chantiers.view` puis la policy (affectations RG7.2, lecture seule RG4.3).
Route::middleware(['auth', 'verified', 'can:chantiers.view'])->prefix('chantiers')->name('chantiers.')->group(function () {
    Route::get('/', [ChantierController::class, 'index'])->name('index');
    Route::get('creer', [ChantierController::class, 'create'])->name('create');
    Route::post('/', [ChantierController::class, 'store'])->name('store');
    Route::get('{chantier}', [ChantierController::class, 'show'])->whereNumber('chantier')->name('show');
    Route::get('{chantier}/modifier', [ChantierController::class, 'edit'])->whereNumber('chantier')->name('edit');
    Route::put('{chantier}', [ChantierController::class, 'update'])->whereNumber('chantier')->name('update');
    Route::post('{chantier}/equipe', [ChantierController::class, 'assign'])->whereNumber('chantier')->name('team.assign');

    Route::post('{chantier}/taches', [PlanningController::class, 'store'])->whereNumber('chantier')->name('tasks.store');
    Route::post('{chantier}/planning/import', [PlanningController::class, 'import'])->whereNumber('chantier')->name('planning.import');

    Route::get('{chantier}/avancement', [ProgressController::class, 'create'])->whereNumber('chantier')->name('avancement');
    Route::post('{chantier}/avancement', [ProgressController::class, 'store'])->whereNumber('chantier')->name('avancement.store');

    Route::get('{chantier}/rapport', [DailyReportController::class, 'create'])->whereNumber('chantier')->name('rapport');
    Route::post('{chantier}/rapport', [DailyReportController::class, 'store'])->whereNumber('chantier')->name('rapport.store');
});
