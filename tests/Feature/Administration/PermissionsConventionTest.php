<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('sème une permission <module>.<action> pour chacun des 15 modules', function () {
    expect(Module::cases())->toHaveCount(15);

    $names = Permission::query()->pluck('name')->all();

    expect($names)->toHaveCount(15 * count(PermissionAction::cases()));

    foreach (Module::cases() as $module) {
        foreach (PermissionAction::cases() as $action) {
            expect($names)->toContain("{$module->value}.{$action->value}");
        }
    }
});

it('sème les 10 profils du cahier des charges comme rôles', function () {
    expect(RoleName::cases())->toHaveCount(10);
    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toEqual(collect(RoleName::cases())->map->value->sort()->values()->all());
});

it('ne réinitialise pas les droits ajustés quand on rejoue le seeder', function () {
    $role = Role::findByName(RoleName::Magasinier->value);
    $role->givePermissionTo('finance.view');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($role->fresh()->hasPermissionTo('finance.view'))->toBeTrue();
});

it('applique la matrice de droits du cahier des charges', function (RoleName $role, string $permission, bool $expected) {
    expect(Role::findByName($role->value)->hasPermissionTo($permission))->toBe($expected);
})->with([
    'admin configure l\'administration' => [RoleName::Administrateur, 'administration.update', true],
    'admin lit tous les modules' => [RoleName::Administrateur, 'finance.view', true],
    'admin ne valide pas les achats' => [RoleName::Administrateur, 'achats.validate', false],
    'direction générale valide partout' => [RoleName::DirectionGenerale, 'achats.validate', true],
    'direction générale ne crée pas' => [RoleName::DirectionGenerale, 'achats.create', false],
    'direction technique valide les achats' => [RoleName::DirectionTechnique, 'achats.validate', true],
    'achats tient le référentiel' => [RoleName::ServiceAchatsBtp, 'achats.update', true],
    'conducteur valide les demandes de ses chefs' => [RoleName::ConducteurTravaux, 'achats.validate', true],
    'chef de chantier ne valide pas' => [RoleName::ChefChantier, 'achats.validate', false],
    'chef de chantier ne voit pas l\'administration' => [RoleName::ChefChantier, 'administration.view', false],
    'magasinier gère les stocks' => [RoleName::Magasinier, 'stocks.create', true],
    'comptabilité ne touche pas aux stocks' => [RoleName::Comptabilite, 'stocks.view', false],
    'finance voit les marchés' => [RoleName::Finance, 'marches.view', true],
    'RH gère la main-d\'œuvre' => [RoleName::RessourcesHumaines, 'main_oeuvre.update', true],
]);
