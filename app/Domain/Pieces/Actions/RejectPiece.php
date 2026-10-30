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
 * Rejet motivé d'une pièce à l'étape courante : l'auteur peut la corriger et la soumettre à nouveau.
 */
final class RejectPiece
{
    public function __construct(
        private readonly ValidationCircuit $circuit,
        private readonly AlertDispatcher $alerts,
    ) {}

    public function execute(Piece $piece, User $by, string $comment): Piece
    {
        if (trim($comment) === '') {
            throw ValidationException::withMessages(['comment' => 'Le motif du rejet est obligatoire.']);
        }

        return DB::transaction(function () use ($piece, $by, $comment): Piece {
            $piece = Piece::query()->lockForUpdate()->findOrFail($piece->id);

            if ($piece->status !== PieceStatus::EnValidation) {
                throw ValidationException::withMessages(['piece' => 'Cette pièce n\'attend plus de validation.']);
            }

            $step = $this->circuit->currentStep($piece);

            if ($step === null || ! $this->circuit->canAct($by, $piece)) {
                throw ValidationException::withMessages(['piece' => 'Vous ne pouvez pas rejeter cette pièce à cette étape.']);
            }

            $this->circuit->record($piece, 'rejetee', $by, $step, $comment);
            $piece->update(['status' => PieceStatus::Rejetee, 'current_step' => null]);

            $this->alerts->send(User::query()->whereKey($piece->requester_id)->get(), 'decision', $piece->kind->label().' rejetée', "{$piece->number} : {$comment}", '/pieces/'.$piece->kind->value.'/'.$piece->id, null, $by);

            return $piece;
        });
    }
}
