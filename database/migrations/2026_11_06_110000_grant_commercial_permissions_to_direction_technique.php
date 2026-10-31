<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Droits ajoutés au rôle existant (le seeder ne touche pas aux rôles déjà créés). */
    private const GRANTS = [
        'clients.view', 'clients.create', 'clients.update',
        'marches.view', 'marches.create', 'marches.update', 'marches.validate',
        'situations.view', 'situations.create', 'situations.update', 'situations.validate',
    ];

    public function up(): void
    {
        $role = Role::query()->where('name', RoleName::DirectionTechnique->value)->where('guard_name', 'web')->first();

        if ($role !== null) {
            foreach (self::GRANTS as $name) {
                Permission::findOrCreate($name, 'web');
                $role->givePermissionTo($name);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', RoleName::DirectionTechnique->value)->where('guard_name', 'web')->first();
        $role?->revokePermissionTo(self::GRANTS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
