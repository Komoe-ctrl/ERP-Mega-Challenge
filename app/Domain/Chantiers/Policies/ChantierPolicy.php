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

    /** RG4.3 : un chantier clôturé, ou dont le projet est clôturé ou abandonné, est en lecture seule. */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Chantier
            && ! $model->isReadOnly()
            && $this->allows($user, PermissionAction::Update);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
