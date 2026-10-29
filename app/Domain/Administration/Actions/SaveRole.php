<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\RoleData;
use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Crée ou met à jour un rôle et ses permissions (RG1.2 : droits ajustables
 * sans développement). Seules les permissions de la convention
 * `<module>.<action>` sont acceptées.
 */
final class SaveRole
{
    public function execute(RoleData $data, ?Role $role = null): Role
    {
        $known = Module::allPermissions();
        $permissions = array_values(array_intersect($data->permissions, $known));

        return DB::transaction(function () use ($data, $role, $permissions): Role {
            if ($role === null) {
                /** @var Role $role */
                $role = Role::query()->create(['name' => $data->name, 'guard_name' => 'web']);
            } elseif ($role->profile() === null) {
                // Les 10 profils du cahier des charges gardent leur nom.
                $role->update(['name' => $data->name]);
            }

            foreach ($permissions as $name) {
                Permission::findOrCreate($name, 'web');
            }

            $role->syncPermissions($permissions);

            return $role;
        });
    }
}
