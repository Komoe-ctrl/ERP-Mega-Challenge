<?php

declare(strict_types=1);

namespace App\Domain\Situations\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Paiement reçu d'un client sur une situation validée.
 *
 * @property int $id
 * @property int $situation_id
 * @property CarbonInterface $paid_at
 * @property int $amount
 * @property string|null $reference
 * @property int|null $recorded_by
 */
#[Fillable(['situation_id', 'paid_at', 'amount', 'reference', 'recorded_by'])]
class SituationPayment extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('situations')->logOnly(['situation_id', 'paid_at', 'amount', 'reference'])->logOnlyDirty();
    }

    /** @return BelongsTo<Situation, $this> */
    public function situation(): BelongsTo
    {
        return $this->belongsTo(Situation::class);
    }
}
