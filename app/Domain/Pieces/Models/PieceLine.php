<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une pièce : désignation, quantité, prix unitaire, montant (calculé), rubrique budgétaire.
 *
 * @property int $id
 * @property int $piece_id
 * @property string $designation
 * @property string $quantity
 * @property int $unit_price
 * @property int $amount
 * @property int $rubrique_id
 */
#[Fillable(['piece_id', 'designation', 'quantity', 'unit_price', 'amount', 'rubrique_id'])]
class PieceLine extends Model
{
    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'amount' => 'integer'];
    }

    /** @return BelongsTo<Piece, $this> */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    /** @return BelongsTo<Rubrique, $this> */
    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(Rubrique::class);
    }
}
