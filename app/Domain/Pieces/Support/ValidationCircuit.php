<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Support;

use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\ValidationEvent;
use App\Domain\Pieces\Models\ValidationStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Circuit de validation paramétré dans Administration, appliqué à toutes les pièces.
 * Une étape est portée par un rôle ; l'auteur d'une pièce ne la valide jamais lui-même.
 */
final class ValidationCircuit
{
    /**
     * Étapes actives, dans l'ordre.
     *
     * @return Collection<int, ValidationStep>
     */
    public function activeSteps(): Collection
    {
        return ValidationStep::query()->where('is_active', true)->orderBy('position')->get();
    }

    /** Étape que la pièce attend actuellement. */
    public function currentStep(Piece $piece): ?ValidationStep
    {
        if ($piece->status !== PieceStatus::EnValidation || $piece->current_step === null) {
            return null;
        }

        return ValidationStep::query()->where('position', $piece->current_step)->first();
    }

    /** Étape active qui suit la position donnée ; null si c'était la dernière. */
    public function stepAfter(int $position): ?ValidationStep
    {
        return ValidationStep::query()->where('is_active', true)->where('position', '>', $position)->orderBy('position')->first();
    }

    /**
     * Le compte peut-il franchir l'étape courante de cette pièce ?
     * Il doit porter le rôle de l'étape, et ne pas être l'auteur de la pièce.
     */
    public function canAct(User $user, Piece $piece): bool
    {
        $step = $this->currentStep($piece);

        return $step !== null
            && $user->is_active
            && $user->id !== $piece->requester_id
            && $user->hasRole($step->role_name);
    }

    /** Le compte tient-il un rôle de l'une des étapes du circuit ? (il peut alors consulter les pièces à valider) */
    public function holdsAnyStepRole(User $user): bool
    {
        return ValidationStep::query()->where('is_active', true)->pluck('role_name')->contains(
            fn (mixed $role): bool => is_string($role) && $user->hasRole($role),
        );
    }

    /** Inscrit un événement dans l'historique du circuit (lu par « Suivi workflow »). */
    public function record(Model $subject, string $action, ?User $by, ?ValidationStep $step = null, ?string $comment = null): ValidationEvent
    {
        return ValidationEvent::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'step_position' => $step?->position,
            'step_label' => $step?->label,
            'action' => $action,
            'user_id' => $by?->id,
            'role_name' => $step?->role_name,
            'comment' => $comment,
        ]);
    }

    /**
     * Comptes actifs porteurs du rôle d'une étape (destinataires de l'alerte « à valider »).
     *
     * @return Collection<int, User>
     */
    public function holdersOf(ValidationStep $step): Collection
    {
        return User::query()->where('is_active', true)->role($step->role_name)->get();
    }
}
