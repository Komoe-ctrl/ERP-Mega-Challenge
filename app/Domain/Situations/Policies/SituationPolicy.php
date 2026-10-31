<?php

declare(strict_types=1);

namespace App\Domain\Situations\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Domain\Situations\Models\Situation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class SituationPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Situations;
    }

    /** RG11.3 : seule une situation en brouillon se modifie. */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Situation
            && $model->isDraft()
            && $this->allows($user, PermissionAction::Update);
    }

    /** Séparation des tâches : l'auteur d'une situation ne la valide pas. */
    public function validate(User $user, Model $model): bool
    {
        return $model instanceof Situation
            && $model->isDraft()
            && $this->canValidateOthers($user, $model);
    }

    /** Enregistrement d'un paiement : situation validée, droit de modification. */
    public function pay(User $user, Situation $situation): bool
    {
        return $situation->status === Situation::VALIDATED && $this->allows($user, PermissionAction::Update);
    }

    /** Une situation n'est jamais supprimée. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
