<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\RoleName;

// Double authentification obligatoire pour Direction, Finance et Administrateur.

it('renvoie un administrateur sans 2FA vers la page de sécurité', function () {
    $admin = userWithRole(RoleName::Administrateur);

    $this->actingAs($admin)->get(route('administration.users.index'))
        ->assertRedirect(route('security.edit'));
});

it('laisse passer un administrateur qui a confirmé sa 2FA', function () {
    $this->actingAs(adminUser())->get(route('administration.users.index'))->assertOk();
});

it('autorise la page de sécurité et la déconnexion pour pouvoir activer la 2FA', function () {
    $finance = userWithRole(RoleName::Finance);

    $this->actingAs($finance)->get(route('profile.edit'))->assertOk();
    $this->actingAs($finance)->post(route('logout'))->assertRedirect();
});

it('n\'impose pas la 2FA aux autres profils', function () {
    $chef = userWithRole(RoleName::ChefChantier);

    $this->actingAs($chef)->get(route('dashboard'))->assertOk();
});

it('peut être désactivée par configuration (environnement local)', function () {
    config(['erp.require_two_factor' => false]);
    $admin = userWithRole(RoleName::Administrateur);

    $this->actingAs($admin)->get(route('administration.users.index'))->assertOk();
});
