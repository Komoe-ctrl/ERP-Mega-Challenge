<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Actions;

use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Support\ValidationCircuit;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Franchissement de l'étape courante du circuit : validation du chef de service, puis approbation
 * du Directeur, etc. L'utilisateur doit porter le rôle de l'étape et ne pas être l'auteur de la pièce.
 * À la dernière étape active, la pièce est approuvée.
 */
final class AdvancePiece
{
    public function __construct(
        private readonly ValidationCircuit $circuit,
        private readonly AlertDispatcher $alerts,
    ) {}

    public function execute(Piece $piece, User $by, ?string $comment = null): Piece
    {
        return DB::transaction(function () use ($piece, $by, $comment): Piece {
            // Relecture sous verrou : une décision concurrente a pu intervenir.
            $piece = Piece::query()->lockForUpdate()->findOrFail($piece->id);

            if ($piece->status !== PieceStatus::EnValidation) {
                throw ValidationException::withMessages(['piece' => 'Cette pièce n\'attend plus de validation.']);
            }

            $step = $this->circuit->currentStep($piece);

            if ($step === null) {
                throw ValidationException::withMessages(['piece' => 'L\'étape de validation de cette pièce n\'existe plus.']);
            }

            if ($by->id === $piece->requester_id) {
                throw ValidationException::withMessages(['piece' => 'Vous ne pouvez pas valider votre propre pièce (séparation des tâches).']);
            }

            if (! $this->circuit->canAct($by, $piece)) {
                throw ValidationException::withMessages(['piece' => "Cette étape (« {$step->label} ») est réservée au rôle « {$step->role_name} »."]);
            }

            $next = $this->circuit->stepAfter($step->position);
            $this->circuit->record($piece, $next === null ? 'approuvee' : 'validee', $by, $step, $comment);

            if ($next === null) {
                $piece->update(['status' => PieceStatus::Approuvee, 'current_step' => null]);
                $this->alerts->send(User::query()->whereKey($piece->requester_id)->get(), 'decision', $piece->kind->label().' approuvée', "{$piece->number} — {$piece->title}", '/pieces/'.$piece->kind->value.'/'.$piece->id, null, $by);

                return $piece;
            }

            $piece->update(['current_step' => $next->position]);
            $this->alerts->send($this->circuit->holdersOf($next), 'demande', $piece->kind->label().' à valider', "{$piece->number} — {$piece->title}", '/pieces/'.$piece->kind->value.'/'.$piece->id, null, $by);

            return $piece;
        });
    }
}
