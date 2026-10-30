<?php

declare(strict_types=1);

namespace App\Domain\Achats\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * RG8.15 : tout demandeur peut signaler un article manquant ; seul le Service
 * Achats BTP reçoit et traite ces demandes.
 */
final class MissingArticleReportPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Achats;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::ServiceAchatsBtp->value) && $this->allows($user, PermissionAction::View);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole(RoleName::ServiceAchatsBtp->value) && $this->allows($user, PermissionAction::Update);
    }
}
