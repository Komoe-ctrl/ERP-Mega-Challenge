<?php

declare(strict_types=1);

namespace App\Domain\Devis\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'ouvrage d'un devis.
 *
 * @property int $id
 * @property int $quote_id
 * @property int|null $ouvrage_id
 * @property int $position
 * @property string $label
 * @property string $unit
 * @property string $quantity
 * @property int $unit_price
 * @property string|null $components
 */
#[Fillable(['quote_id', 'ouvrage_id', 'position', 'label', 'unit', 'quantity', 'unit_price', 'components'])]
class QuoteWork extends Model
{
    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function total(): int
    {
        return (int) round((float) $this->quantity * $this->unit_price);
    }
}
