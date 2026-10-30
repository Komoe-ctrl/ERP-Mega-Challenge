<?php

declare(strict_types=1);

namespace App\Domain\Budget\Policies;

use App\Domain\Budget\Models\BudgetRevision;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;

/**
 * Droits sur le budget d'un chantier (M10). La matrice du cahier donne la
 * lecture du budget à la plupart des profils et aucune écriture dédiée : les
 * écritures s'appuient sur le droit d'écrire sur le chantier (RG7.2, RG4.3).
 *
 * - voir : droit de lecture sur Budget et chantier visible ;
 * - structurer (créer un poste, demander une révision) : droit d'écrire sur le
 *   chantier, hors chef de chantier ;
 * - saisir une dépense diverse (RG10.4) : tout profil qui écrit sur le chantier ;
 * - décider d'une révision (RG10.2) : droit de validation Budget (Direction) et
 *   jamais l'auteur de la demande.
 */
final class BudgetLinePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Budget;
    }

    public function viewBudget(User $user, Chantier $chantier): bool
    {
        return $this->allows($user, PermissionAction::View) && $chantier->isVisibleTo($user);
    }

    public function manage(User $user, Chantier $chantier): bool
    {
        return $user->can('update', $chantier) && ! $user->hasRole(RoleName::ChefChantier->value);
    }

    public function recordExpense(User $user, Chantier $chantier): bool
    {
        return $user->can('update', $chantier);
    }

    public function decideRevision(User $user, BudgetRevision $revision): bool
    {
        return $revision->status === BudgetRevision::PENDING
            && $this->canValidateOthers($user, $revision, 'requested_by');
    }
}
