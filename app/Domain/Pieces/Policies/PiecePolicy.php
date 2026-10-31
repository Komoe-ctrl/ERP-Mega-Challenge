<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Policies;

use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Support\ValidationCircuit;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Models\User;

/**
 * Droits sur les pièces : le module du type de pièce (Achats pour les notes de frais, Finance pour
 * les recettes et les pièces de caisse) donne la lecture et la saisie ; le circuit donne le droit
 * de valider (par rôle d'étape). Un porteur d'un rôle du circuit peut consulter les pièces à valider.
 */
final class PiecePolicy
{
    public function __construct(private readonly ValidationCircuit $circuit) {}

    /** Droit du module du type de pièce (ex. `achats.create` pour une note de frais). */
    public function can(User $user, PieceKind $kind, PermissionAction $action): bool
    {
        return $user->is_active && $user->can($kind->module()->permission($action));
    }

    public function viewAny(User $user, PieceKind $kind): bool
    {
        return $this->can($user, $kind, PermissionAction::View) || $this->circuit->holdsAnyStepRole($user);
    }

    public function view(User $user, Piece $piece): bool
    {
        return $piece->requester_id === $user->id || $this->viewAny($user, $piece->kind);
    }

    public function create(User $user, PieceKind $kind): bool
    {
        return $this->can($user, $kind, PermissionAction::Create);
    }

    /** Modifier, supprimer ou soumettre : l'auteur, tant que la pièce n'est pas dans le circuit. */
    public function update(User $user, Piece $piece): bool
    {
        return $user->is_active && $piece->requester_id === $user->id && $piece->status->isEditable();
    }

    public function delete(User $user, Piece $piece): bool
    {
        return $this->update($user, $piece);
    }

    public function submit(User $user, Piece $piece): bool
    {
        return $this->update($user, $piece);
    }

    /** Rembourser une note de frais approuvée : droit d'écriture sur la Finance (Comptabilité, Finance). */
    public function reimburse(User $user, Piece $piece): bool
    {
        return $piece->kind === PieceKind::NoteFrais
            && $piece->status === PieceStatus::Approuvee
            && $piece->invoice_number === null
            && $user->is_active
            && $user->can(Module::Finance->permission(PermissionAction::Update));
    }

    /** Le compte voit-il les remboursements ? (Comptabilité, Finance, Direction) */
    public function viewReimbursements(User $user): bool
    {
        return $user->is_active && $user->can(Module::Finance->permission(PermissionAction::View));
    }

    /** Valider ou rejeter à l'étape courante : rôle de l'étape, jamais l'auteur. */
    public function decide(User $user, Piece $piece): bool
    {
        return $this->circuit->canAct($user, $piece);
    }
}
