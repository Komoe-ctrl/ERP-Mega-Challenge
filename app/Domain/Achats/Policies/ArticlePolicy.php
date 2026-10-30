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
 * RG8.13 et CA8.3 : seul le Service Achats BTP crée, modifie ou désactive un
 * article, ni par l'interface ni par la requête. Le droit `achats.update`
 * d'autres profils (conducteur, chef, magasinier) ne suffit donc pas.
 */
final class ArticlePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Achats;
    }

    public function create(User $user): bool
    {
        return $this->purchasing($user, PermissionAction::Create);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->purchasing($user, PermissionAction::Update);
    }

    /** RG8.14 : un article est désactivé, jamais supprimé. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function purchasing(User $user, PermissionAction $action): bool
    {
        return $user->hasRole(RoleName::ServiceAchatsBtp->value) && $this->allows($user, $action);
    }
}
