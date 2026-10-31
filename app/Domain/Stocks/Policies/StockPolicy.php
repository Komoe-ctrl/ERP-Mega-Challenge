<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Models\User;

/**
 * Droits du module Stocks : lecture (état, mouvements, inventaires), saisie des mouvements et des
 * inventaires, validation des pertes, ajustements et inventaires (droit « valider »).
 * Le valideur n'est jamais l'auteur (vérifié par les actions).
 */
final class StockPolicy
{
    public function view(User $user): bool
    {
        return $this->allows($user, PermissionAction::View);
    }

    public function record(User $user): bool
    {
        return $this->allows($user, PermissionAction::Create);
    }

    public function configure(User $user): bool
    {
        return $this->allows($user, PermissionAction::Update);
    }

    public function validate(User $user): bool
    {
        return $this->allows($user, PermissionAction::Validate);
    }

    private function allows(User $user, PermissionAction $action): bool
    {
        return $user->is_active && $user->can(Module::Stocks->permission($action));
    }
}
