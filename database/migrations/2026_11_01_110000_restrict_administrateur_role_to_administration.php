<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // L'Administrateur ne gère plus que l'Administration : il ne voit aucun module métier.
        // Le seeder ne touche pas aux rôles existants, on aligne donc la base ici.
        $role = Role::query()->where('name', RoleName::Administrateur->value)->where('guard_name', 'web')->first();

        if ($role !== null) {
            $permissions = Permission::query()
                ->whereIn('name', RoleName::Administrateur->defaultPermissions())
                ->where('guard_name', 'web')
                ->get();
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', RoleName::Administrateur->value)->where('guard_name', 'web')->first();

        if ($role !== null) {
            $role->syncPermissions(Permission::query()->where('name', 'like', '%.view')->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
