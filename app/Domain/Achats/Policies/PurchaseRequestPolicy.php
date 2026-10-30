<?php

declare(strict_types=1);

namespace App\Domain\Achats\Policies;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Droits sur les demandes d'achat.
 *
 * - voir : droit de lecture sur Achats et, pour un conducteur ou un chef,
 *   chantier auquel il est affecté (RG7.2) ;
 * - corriger : l'auteur seulement, tant que la demande est un brouillon ou
 *   renvoyée (RG8.4) ;
 * - annuler : l'auteur seulement, tant qu'elle n'est pas validée (RG8.4) ;
 * - décider (valider, refuser, renvoyer) : droit de validation, jamais sur sa
 *   propre demande (séparation des tâches) et seulement sur un chantier visible.
 *   La validation de la Direction au-delà des seuils (RG8.7) est contrôlée par
 *   l'action, car elle dépend du budget restant au moment de la décision.
 */
final class PurchaseRequestPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Achats;
    }

    public function view(User $user, Model $model): bool
    {
        return $model instanceof PurchaseRequest
            && $this->allows($user, PermissionAction::View)
            && $this->chantierVisible($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof PurchaseRequest
            && $this->allows($user, PermissionAction::Create)
            && $model->requester_id === $user->id
            && $model->status->isEditable()
            && ! $model->site()->isReadOnly();
    }

    public function cancel(User $user, PurchaseRequest $request): bool
    {
        return $this->allows($user, PermissionAction::Create)
            && $request->requester_id === $user->id
            && ! $request->status->isFinal();
    }

    public function decide(User $user, PurchaseRequest $request): bool
    {
        return $request->status->getValue() === 'en_attente'
            && $this->canValidateOthers($user, $request, 'requester_id')
            && $this->chantierVisible($user, $request);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function chantierVisible(User $user, PurchaseRequest $request): bool
    {
        return $request->site()->isVisibleTo($user);
    }
}
