<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// CA1 : un utilisateur sans droit sur un module n'en voit pas le menu, et le
// serveur refuse ses requêtes sur ce module.

it('refuse l\'administration à un utilisateur sans le droit (403)', function (string $routeName) {
    $chef = userWithRole(RoleName::ChefChantier);

    $this->actingAs($chef)->get(route($routeName))->assertForbidden();
})->with([
    'liste des utilisateurs' => 'administration.users.index',
    'création d\'un utilisateur' => 'administration.users.create',
    'liste des rôles' => 'administration.roles.index',
]);

it('refuse aussi les écritures sans le droit, même en forgeant la requête', function () {
    $chef = userWithRole(RoleName::ChefChantier);

    $this->actingAs($chef)->post(route('administration.users.store'), [
        'name' => 'Pirate',
        'email' => 'pirate@example.com',
        'password' => 'Secret!12345',
        'is_active' => true,
    ])->assertForbidden();

    expect(User::where('email', 'pirate@example.com')->exists())->toBeFalse();
});

it('redirige un visiteur non connecté vers la connexion', function () {
    $this->get(route('administration.users.index'))->assertRedirect(route('login'));
});

it('cache le menu Administration à un utilisateur sans droit', function () {
    $chef = userWithRole(RoleName::ChefChantier);

    $this->actingAs($chef)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('navigation', []));
});

it('affiche le menu Administration à un administrateur', function () {
    $this->actingAs(adminUser())->get(route('administration.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('navigation', 1)
            ->where('navigation.0.key', 'administration')
            ->where('navigation.0.href', '/administration/users'));
});

it('n\'affiche pas un module désactivé même si le droit existe', function () {
    config(['erp.modules_enabled' => []]);

    $this->actingAs(adminUser())->get(route('administration.home'))
        ->assertInertia(fn (Assert $page) => $page->where('navigation', []));
});

it('expose les rôles et permissions de l\'utilisateur à l\'interface', function () {
    $this->actingAs(adminUser())->get(route('administration.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['administrateur'])
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('administration.update')));
});

it('coupe l\'accès d\'un compte désactivé même avec une session ouverte', function () {
    $user = adminUser();
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get(route('administration.users.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});
