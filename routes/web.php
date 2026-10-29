<?php

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// L'accueil n'est pas une page publique : on renvoie vers la connexion ou le tableau de bord.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('notifications', 'Notifications/Index')->name('notifications.index');
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

// --- V1 : Projets (M4) ---------------------------------------------------
Route::middleware(['auth', 'verified', 'can:projets.view'])->prefix('projets')->name('projets.')->group(function () {
    Route::inertia('/', 'Projets/Index')->name('index');
    Route::inertia('creer', 'Projets/Form')->name('create');
    Route::get('{id}', fn (int $id) => Inertia::render('Projets/Show', ['id' => $id]))->whereNumber('id')->name('show');
    Route::get('{id}/modifier', fn (int $id) => Inertia::render('Projets/Form', ['id' => $id]))->whereNumber('id')->name('edit');
});

// --- V1 : Chantiers (M7) ---------------------------------------------------
Route::middleware(['auth', 'verified', 'can:chantiers.view'])->prefix('chantiers')->name('chantiers.')->group(function () {
    Route::inertia('/', 'Chantiers/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Chantiers/Show', ['id' => $id]))->whereNumber('id')->name('show');
    Route::get('{id}/avancement', fn (int $id) => Inertia::render('Chantiers/Avancement', ['id' => $id]))->whereNumber('id')->name('avancement');
    Route::get('{id}/rapport', fn (int $id) => Inertia::render('Chantiers/Rapport', ['id' => $id]))->whereNumber('id')->name('rapport');
});

// --- V1 : Achats — demandes et référentiel des prix (M8) --------------------
Route::middleware(['auth', 'verified', 'can:achats.view'])->prefix('achats')->name('achats.')->group(function () {
    Route::inertia('demandes', 'Achats/Demandes/Index')->name('demandes');
    Route::inertia('demandes/creer', 'Achats/Demandes/Create')->name('demandes.create');
    Route::get('demandes/{id}', fn (int $id) => Inertia::render('Achats/Demandes/Show', ['id' => $id]))->whereNumber('id')->name('demandes.show');
    Route::inertia('a-valider', 'Achats/Demandes/AValider')->name('a-valider');
    // V2 : processus achat complet (consultation, commande, réception, facture)
    Route::inertia('consultations', 'Achats/Consultations/Index')->name('consultations');
    Route::get('consultations/{id}', fn (int $id) => Inertia::render('Achats/Consultations/Show', ['id' => $id]))->whereNumber('id')->name('consultations.show');
    Route::inertia('commandes', 'Achats/Commandes/Index')->name('commandes');
    Route::get('commandes/{id}', fn (int $id) => Inertia::render('Achats/Commandes/Show', ['id' => $id]))->whereNumber('id')->name('commandes.show');
    Route::inertia('receptions', 'Achats/Receptions/Index')->name('receptions');
    Route::inertia('factures', 'Achats/Factures/Index')->name('factures');
    Route::inertia('referentiel', 'Achats/Referentiel/Index')->name('referentiel');
    Route::inertia('referentiel/creer', 'Achats/Referentiel/Form')->name('referentiel.create');
    Route::get('referentiel/{id}', fn (int $id) => Inertia::render('Achats/Referentiel/Form', ['id' => $id]))->whereNumber('id')->name('referentiel.edit');
});

// --- V2 : Fournisseurs (M3) ---------------------------------------------------
Route::middleware(['auth', 'verified', 'can:fournisseurs.view'])->prefix('fournisseurs')->name('fournisseurs.')->group(function () {
    Route::inertia('/', 'Fournisseurs/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Fournisseurs/Show', ['id' => $id]))->whereNumber('id')->name('show');
});

// --- V2 : Stocks BTP (M9) ----------------------------------------------------
Route::middleware(['auth', 'verified', 'can:stocks.view'])->prefix('stocks')->name('stocks.')->group(function () {
    Route::inertia('/', 'Stocks/Index')->name('index');
    Route::inertia('mouvements', 'Stocks/Mouvements')->name('mouvements');
    Route::inertia('inventaire', 'Stocks/Inventaire')->name('inventaire');
});

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
    Route::inertia('avances', 'Rh/Avances')->name('avances');
});
Route::middleware(['auth', 'verified', 'can:engins.view'])->prefix('engins')->name('engins.')->group(function () {
    Route::inertia('/', 'Engins/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Engins/Show', ['id' => $id]))->whereNumber('id')->name('show');
});
Route::middleware(['auth', 'verified', 'can:finance.view'])->prefix('finance')->name('finance.')->group(function () {
    Route::inertia('/', 'Finance/Index')->name('index');
    Route::inertia('caisse', 'Finance/Operations', ['account' => 'caisse'])->name('caisse');
    Route::inertia('banque', 'Finance/Operations', ['account' => 'banque'])->name('banque');
    Route::inertia('rentabilite', 'Finance/Rentabilite')->name('rentabilite');
    Route::inertia('tresorerie', 'Finance/Tresorerie')->name('tresorerie');
});

// --- V1 : Budget chantier (M10) -------------------------------------------
Route::middleware(['auth', 'verified', 'can:budget.view'])->prefix('budget')->name('budget.')->group(function () {
    Route::inertia('/', 'Budget/Index')->name('index');
    Route::get('{id}', fn (int $id) => Inertia::render('Budget/Show', ['id' => $id]))->whereNumber('id')->name('show');
});

// --- V1 : Administration (écrans complémentaires aux utilisateurs/rôles) ---
Route::middleware(['auth', 'verified', 'can:administration.view'])->prefix('administration')->name('administration.')->group(function () {
    Route::inertia('organisation', 'Administration/Organisation')->name('organisation');
    Route::inertia('parametres', 'Administration/Parametres')->name('parametres');
    Route::inertia('journal', 'Administration/Journal')->name('journal');
    // Matrice rôles x permissions : données réelles (base), lecture seule.
    Route::get('matrice', fn () => Inertia::render('Administration/Matrice', [
        'roles' => Role::query()->with('permissions')->orderBy('id')->get()->map(fn (Role $r): array => [
            'name' => $r->name,
            'label' => $r->label(),
            'permissions' => $r->permissions->pluck('name')->values()->all(),
        ])->values(),
        'modules' => array_map(fn (Module $m): array => ['value' => $m->value, 'label' => $m->label()], Module::cases()),
        'actions' => array_map(fn (PermissionAction $a): array => ['value' => $a->value, 'label' => $a->label()], PermissionAction::cases()),
    ]))->name('matrice');
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
