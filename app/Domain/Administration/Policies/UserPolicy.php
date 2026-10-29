<?php

declare(strict_types=1);

namespace App\Domain\Administration\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class UserPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Administration;
    }

    /** Un compte est désactivé, jamais supprimé : la suppression est interdite. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    /** On ne désactive pas son propre compte. */
    public function deactivate(User $user, User $target): bool
    {
        return $user->isNot($target) && $this->allows($user, PermissionAction::Update);
    }

    public function reactivate(User $user, User $target): bool
    {
        return $this->allows($user, PermissionAction::Update);
    }
}
