<?php

declare(strict_types=1);

namespace App\Domain\Marches\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Droits du module Marchés. La validation d'un avenant (droit « valider ») est contrôlée par
 * l'action : le valideur n'est jamais l'auteur de l'avenant.
 */
final class ContractPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Marches;
    }

    public function validate(User $user, Model $model): bool
    {
        return $this->allows($user, PermissionAction::Validate);
    }

    /** Un marché n'est jamais supprimé. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
