<?php

use App\Domain\Shared\Enums\RoleName;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyScreenController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PosteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// L'accueil n'est pas une page publique : on renvoie vers la connexion ou le tableau de bord.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('pointage', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('poste', [PosteController::class, 'update'])->name('poste.update');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/lues', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::put('notifications/canaux', [NotificationController::class, 'channels'])->name('notifications.channels');
    Route::post('notifications/{id}/lue', [NotificationController::class, 'read'])->name('notifications.read');
});

/*
|--------------------------------------------------------------------------
| ROUTES TEMPORAIRES DU FRONTEND (branche feature/frontend)
|--------------------------------------------------------------------------
|
| Ces routes ne servent qu'à afficher les écrans avec les données fictives de
| resources/js/mocks. Elles seront remplacées, module par module, par de vrais
| contrôleurs (app/Http/Controllers/<Module>) qui appelleront des Actions.
| Les droits restent vérifiés côté serveur : `can:<module>.view`.
|
*/

// --- V3 : Clients (M2), Devis (M5), Marchés (M6), Situations (M11) ---------
Route::middleware(['auth', 'verified', 'can:clients.view'])->prefix('clients')->name('clients.')->group(function () {
    Route::inertia('/', 'Clients/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Clients/Show', ['id' => $id]))->whereNumber('id')->name('show');
});
Route::middleware(['auth', 'verified', 'can:devis.view'])->prefix('devis')->name('devis.')->group(function () {
    Route::inertia('/', 'Devis/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Devis/Show', ['id' => $id]))->whereNumber('id')->name('show');
});
Route::middleware(['auth', 'verified', 'can:marches.view'])->prefix('marches')->name('marches.')->group(function () {
    Route::inertia('/', 'Marches/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Marches/Show', ['id' => $id]))->whereNumber('id')->name('show');
});
Route::middleware(['auth', 'verified', 'can:situations.view'])->prefix('situations')->name('situations.')->group(function () {
    Route::inertia('/', 'Situations/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Situations/Show', ['id' => $id]))->whereNumber('id')->name('show');
});

// --- V4 : Main-d'œuvre (M12), RH (M13), Engins (M14), Finance (M15) --------
Route::middleware(['auth', 'verified', 'can:main_oeuvre.view'])->prefix('main-oeuvre')->name('main_oeuvre.')->group(function () {
    Route::inertia('/', 'MainOeuvre/Pointage')->name('pointage');
});
Route::middleware(['auth', 'verified', 'can:rh.view'])->prefix('rh')->name('rh.')->group(function () {
    Route::inertia('/', 'Rh/Index')->name('index');
    Route::inertia('conges', 'Rh/Conges')->name('conges');
    Route::get('{id}', fn (int $id) => Inertia::render('Rh/Employe', ['id' => $id]))->whereNumber('id')->name('show');
    Route::get('{id}/bulletin', fn (int $id) => Inertia::render('Rh/Bulletin', ['id' => $id]))->whereNumber('id')->name('bulletin');
    Route::inertia('avances', 'Rh/Avances')->name('avances');
});
Route::middleware(['auth', 'verified', 'can:engins.view'])->prefix('engins')->name('engins.')->group(function () {
    Route::inertia('/', 'Engins/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Engins/Show', ['id' => $id]))->whereNumber('id')->name('show');
});
Route::middleware(['auth', 'verified', 'can:finance.view'])->prefix('finance')->name('finance.')->group(function () {
    Route::inertia('rentabilite', 'Finance/Rentabilite')->name('rentabilite');
    Route::inertia('tresorerie', 'Finance/Tresorerie')->name('tresorerie');
});

// --- Écrans repris de l'application de bureau actuelle (maquette) --------------
Route::middleware(['auth', 'verified'])->prefix('ecrans')->group(function () {
    Route::get('{slug}', LegacyScreenController::class)->where('slug', '[a-z0-9-]+')->name('ecrans');
});

/*
|--------------------------------------------------------------------------
| DÉMO LOCALE : changer de profil
|--------------------------------------------------------------------------
|
| Permet de parcourir l'ERP avec chacun des 10 profils sans créer 10 comptes.
| Disponible uniquement en environnement local (APP_ENV=local) : en
| production la route répond 404 et le sélecteur n'est pas affiché.
|
*/
Route::post('demo/role', function (Request $request) {
    abort_unless(app()->isLocal(), 404);

    $role = RoleName::from($request->string('role')->toString());
    ($request->user() ?? abort(401))->syncRoles([$role->value]);

    return back();
})->middleware(['auth'])->name('demo.role');

require __DIR__.'/settings.php';
require __DIR__.'/administration.php';
require __DIR__.'/projets.php';
require __DIR__.'/chantiers.php';
require __DIR__.'/achats.php';
require __DIR__.'/budget.php';
require __DIR__.'/fournisseurs.php';
require __DIR__.'/pieces.php';
require __DIR__.'/stocks.php';
