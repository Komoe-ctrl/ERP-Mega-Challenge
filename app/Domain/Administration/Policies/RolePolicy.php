<?php

declare(strict_types=1);

namespace App\Domain\Administration\Policies;

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class RolePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Administration;
    }

    /** Les 10 profils du cahier des charges ne se suppriment pas ; un rôle encore attribué non plus. */
    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Role
            && $model->profile() === null
            && $model->users()->doesntExist()
            && $this->allows($user, PermissionAction::Delete);
    }
}
