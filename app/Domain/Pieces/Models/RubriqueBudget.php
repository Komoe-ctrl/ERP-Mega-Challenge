<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use App\Domain\Administration\Models\FiscalYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Budget d'une rubrique pour un exercice, en FCFA entiers.
 *
 * @property int $id
 * @property int $rubrique_id
 * @property int $fiscal_year_id
 * @property int $amount
 */
#[Fillable(['rubrique_id', 'fiscal_year_id', 'amount'])]
class RubriqueBudget extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Rubrique, $this> */
    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(Rubrique::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
