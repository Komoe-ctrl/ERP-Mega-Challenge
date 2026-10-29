<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;

it('liste les rôles avec leur nombre d\'utilisateurs', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('administration.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Administration/Roles/Index')
            ->has('roles', 10)
            ->where('roles.0.label', 'Administrateur'));
});

it('crée un rôle avec des permissions de la convention', function () {
    $this->actingAs(adminUser())->post(route('administration.roles.store'), [
        'name' => 'Auditeur',
        'permissions' => ['finance.view', 'budget.view'],
    ])->assertRedirect(route('administration.roles.index'));

    $role = Role::findByName('Auditeur');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toEqual(['budget.view', 'finance.view']);
});

it('refuse une permission hors convention', function () {
    $this->actingAs(adminUser())->post(route('administration.roles.store'), [
        'name' => 'Pirate',
        'permissions' => ['tout.pouvoir'],
    ])->assertSessionHasErrors('permissions.0');

    expect(Role::where('name', 'Pirate')->exists())->toBeFalse();
});

it('ajuste les droits d\'un profil sans développement (RG1.2)', function () {
    $admin = adminUser();
    $role = Role::findByName(RoleName::Magasinier->value);

    $this->actingAs($admin)->put(route('administration.roles.update', $role), [
        'name' => $role->name,
        'permissions' => ['stocks.view', 'stocks.validate'],
    ])->assertRedirect();

    expect($role->fresh()->hasPermissionTo('stocks.validate'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('achats.create'))->toBeFalse();
});

it('conserve le nom d\'un des 10 profils même si on tente de le renommer', function () {
    $admin = adminUser();
    $role = Role::findByName(RoleName::Magasinier->value);

    $this->actingAs($admin)->put(route('administration.roles.update', $role), [
        'name' => 'Autre nom',
        'permissions' => ['stocks.view'],
    ]);

    expect($role->fresh()->name)->toBe(RoleName::Magasinier->value);
});

it('interdit la gestion des rôles sans le droit', function () {
    $conducteur = userWithRole(RoleName::ConducteurTravaux);

    $this->actingAs($conducteur)->post(route('administration.roles.store'), ['name' => 'X', 'permissions' => []])
        ->assertForbidden();
});

it('ne permet pas de supprimer un profil du cahier des charges', function () {
    $admin = adminUser();

    expect($admin->can('delete', Role::findByName(RoleName::Finance->value)))->toBeFalse();
});
