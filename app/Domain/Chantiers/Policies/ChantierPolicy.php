<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Policies;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ChantierPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Chantiers;
    }

    /** RG7.2 : un utilisateur de terrain ne voit que ses chantiers. */
    public function view(User $user, Model $model): bool
    {
        return $model instanceof Chantier
            && $this->allows($user, PermissionAction::View)
            && $model->isVisibleTo($user);
    }

    /**
     * RG4.3 : un chantier clôturé, ou dont le projet est clôturé ou abandonné,
     * est en lecture seule. RG7.2 : un utilisateur de terrain n'écrit que sur ses chantiers.
     */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Chantier
            && ! $model->isReadOnly()
            && $this->allows($user, PermissionAction::Update)
            && $model->isVisibleTo($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
