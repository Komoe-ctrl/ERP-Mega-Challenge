<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Policies\RolePolicy;
use App\Domain\Shared\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rôle (profil utilisateur). Étend le modèle de spatie/laravel-permission pour
 * journaliser les changements. Déclaré dans config/permission.php.
 */
#[UsePolicy(RolePolicy::class)]
class Role extends SpatieRole
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('administration')
            ->logOnly(['name'])
            ->logOnlyDirty();
    }

    /** Profil du cahier des charges correspondant, null pour un rôle créé à la main. */
    public function profile(): ?RoleName
    {
        return RoleName::tryFrom($this->name);
    }

    /** Libellé affichable : celui du profil, sinon le nom saisi. */
    public function label(): string
    {
        return $this->profile()?->label() ?? $this->name;
    }
}
