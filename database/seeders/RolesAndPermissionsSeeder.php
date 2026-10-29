<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions `<module>.<action>` pour les 15 modules, et les 10 profils du
 * cahier des charges comme rôles.
 *
 * La matrice par défaut n'est appliquée qu'à la création d'un rôle : si un
 * administrateur a ajusté les droits (RG1.2), rejouer le seeder ne les écrase pas.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Module::allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (RoleName::cases() as $profile) {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate(['name' => $profile->value, 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($profile->defaultPermissions());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
