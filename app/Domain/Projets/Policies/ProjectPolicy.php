<?php

declare(strict_types=1);

namespace App\Domain\Projets\Policies;

use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ProjectPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Projets;
    }

    /** RG4.3 : un projet clôturé ou abandonné est en lecture seule. */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Project
            && ! $model->isReadOnly()
            && $this->allows($user, PermissionAction::Update);
    }

    /** Un projet n'est jamais supprimé : il est abandonné ou clôturé. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
