<?php

declare(strict_types=1);

namespace App\Domain\Situations\Models;

use App\Domain\Devis\Models\QuoteWork;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avancement cumulé d'un ouvrage du devis du marché dans une situation (RG11.1).
 *
 * @property int $id
 * @property int $situation_id
 * @property int $quote_work_id
 * @property string $progress
 */
#[Fillable(['situation_id', 'quote_work_id', 'progress'])]
class SituationLine extends Model
{
    /** @return BelongsTo<Situation, $this> */
    public function situation(): BelongsTo
    {
        return $this->belongsTo(Situation::class);
    }

    /** @return BelongsTo<QuoteWork, $this> */
    public function work(): BelongsTo
    {
        return $this->belongsTo(QuoteWork::class, 'quote_work_id');
    }
}
