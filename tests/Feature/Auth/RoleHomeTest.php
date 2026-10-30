<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\RoleName;
use App\Http\Responses\RoleHome;
use Inertia\Testing\AssertableInertia as Assert;

// Page d'arrivée : celle du profil, jamais les paramètres de sécurité.

it('envoie chaque profil sur sa page d\'arrivée', function (RoleName $role, string $path) {
    $user = userWithRole($role);

    expect(RoleHome::for($user))->toBe($path);
})->with([
    [RoleName::Administrateur, '/administration'],
    [RoleName::DirectionGenerale, '/dashboard'],
    [RoleName::DirectionTechnique, '/dashboard'],
    [RoleName::ServiceAchatsBtp, '/achats/a-valider'],
    [RoleName::ConducteurTravaux, '/chantiers'],
    [RoleName::ChefChantier, '/chantiers'],
    [RoleName::Magasinier, '/achats/receptions'],
    [RoleName::Comptabilite, '/achats/factures'],
    [RoleName::Finance, '/ecrans/situation-caisse'],
    [RoleName::RessourcesHumaines, '/rh'],
]);

it('redirige l\'administrateur du tableau de bord métier vers l\'accueil Administration', function () {
    $this->actingAs(adminUser())->get(route('dashboard'))->assertRedirect('/administration');
});

it('affiche à l\'administrateur l\'accueil avec les comptes et le journal', function () {
    $this->actingAs(adminUser())->get(route('administration.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Administration/Accueil')
            ->has('stats.users')
            ->has('recentUsers')
            ->has('entries'));
});

it('ne donne à l\'administrateur aucun module métier', function () {
    $admin = adminUser();

    expect($admin->can('administration.view'))->toBeTrue()
        ->and($admin->can('achats.view'))->toBeFalse()
        ->and($admin->can('finance.view'))->toBeFalse()
        ->and($admin->can('chantiers.view'))->toBeFalse();
});
