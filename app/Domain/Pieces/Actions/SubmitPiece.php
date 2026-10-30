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
 * Soumission d'une pièce par son auteur : elle entre dans le circuit à la première étape active.
 */
final class SubmitPiece
{
    public function __construct(
        private readonly ValidationCircuit $circuit,
        private readonly AlertDispatcher $alerts,
    ) {}

    public function execute(Piece $piece, User $by): Piece
    {
        return DB::transaction(function () use ($piece, $by): Piece {
            $piece = Piece::query()->lockForUpdate()->findOrFail($piece->id);

            if ($piece->requester_id !== $by->id) {
                throw ValidationException::withMessages(['piece' => 'Seul l\'auteur peut soumettre sa pièce.']);
            }

            if (! $piece->status->isEditable()) {
                throw ValidationException::withMessages(['piece' => 'Cette pièce est déjà dans le circuit de validation.']);
            }

            if (! $piece->lines()->exists()) {
                throw ValidationException::withMessages(['lines' => 'Saisissez au moins une ligne avant de valider.']);
            }

            $first = $this->circuit->activeSteps()->first();

            if ($first === null) {
                throw ValidationException::withMessages(['piece' => 'Aucune étape de validation n\'est paramétrée.']);
            }

            $piece->update(['status' => PieceStatus::EnValidation, 'current_step' => $first->position]);
            $this->circuit->record($piece, 'soumise', $by, null, null);

            $this->alerts->send(
                $this->circuit->holdersOf($first),
                'demande',
                $piece->kind->label().' à valider',
                "{$piece->number} — {$piece->title}",
                '/pieces/'.$piece->kind->value.'/'.$piece->id,
                null,
                $by,
            );

            return $piece;
        });
    }
}
