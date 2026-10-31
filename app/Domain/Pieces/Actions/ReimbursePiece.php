<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Actions;

use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Support\ValidationCircuit;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remboursement d'une note de frais approuvée : émet la facture de remboursement (numéro unique)
 * et garde la trace de qui a remboursé et quand. Une note ne se rembourse qu'une fois.
 */
final class ReimbursePiece
{
    public function __construct(
        private readonly ValidationCircuit $circuit,
        private readonly AlertDispatcher $alerts,
    ) {}

    public function execute(Piece $piece, User $by): Piece
    {
        return DB::transaction(function () use ($piece, $by): Piece {
            $piece = Piece::query()->lockForUpdate()->findOrFail($piece->id);

            if ($piece->kind !== PieceKind::NoteFrais) {
                throw ValidationException::withMessages(['piece' => 'Seules les notes de frais se remboursent.']);
            }

            if ($piece->status !== PieceStatus::Approuvee) {
                throw ValidationException::withMessages(['piece' => 'Seule une note de frais approuvée peut être remboursée.']);
            }

            if ($piece->invoice_number !== null) {
                throw ValidationException::withMessages(['piece' => 'Cette note de frais est déjà remboursée.']);
            }

            $piece->update([
                'invoice_number' => DocumentNumbers::next('facture_note_frais', 'FN-{YYYY}-{SEQ:4}'),
                'reimbursed_at' => now(),
                'reimbursed_by' => $by->id,
            ]);

            $this->circuit->record($piece, 'remboursee', $by, null, 'Facture '.$piece->invoice_number);

            $this->alerts->send(
                User::query()->whereKey($piece->requester_id)->get(),
                'decision',
                'Note de frais remboursée',
                "{$piece->number} — {$piece->title} ({$piece->invoice_number})",
                '/pieces/'.$piece->kind->value.'/'.$piece->id,
                null,
                $by,
            );

            return $piece;
        });
    }
}
