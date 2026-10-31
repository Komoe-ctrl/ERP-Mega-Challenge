<?php

declare(strict_types=1);

namespace App\Domain\Devis\Policies;

use App\Domain\Devis\Models\Quote;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class QuotePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Devis;
    }

    /** Seul un brouillon est modifiable. */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Quote
            && $model->status->getValue() === 'brouillon'
            && $this->allows($user, PermissionAction::Update);
    }

    /** Séparation des tâches : l'auteur du devis ne le valide ni ne le refuse. */
    public function validate(User $user, Model $model): bool
    {
        return $model instanceof Quote
            && $model->status->getValue() === 'soumis'
            && $this->canValidateOthers($user, $model);
    }

    /** Un devis n'est jamais supprimé : il est refusé puis dupliqué en nouvelle version. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
