<?php

declare(strict_types=1);

namespace App\Domain\Situations\Models;

use App\Domain\Marches\Models\Contract;
use App\Domain\Situations\Policies\SituationPolicy;
use App\Domain\Situations\Support\SituationCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Situation de travaux (M11). Validée, elle est figée : une correction passe par la situation suivante (RG11.3).
 *
 * @property int $id
 * @property int $contract_id
 * @property int $number
 * @property CarbonInterface $situation_date
 * @property string $progress_cumul
 * @property string $previous_progress
 * @property int $market_amount
 * @property string $retention_rate
 * @property string $advance_refund_rate
 * @property string $vat_rate
 * @property string $status
 * @property int|null $created_by
 * @property int|null $validated_by
 * @property CarbonInterface|null $validated_at
 */
#[Fillable(['contract_id', 'number', 'situation_date', 'progress_cumul', 'previous_progress', 'market_amount', 'retention_rate', 'advance_refund_rate', 'vat_rate', 'status', 'created_by', 'validated_by', 'validated_at'])]
#[UsePolicy(SituationPolicy::class)]
class Situation extends Model
{
    use LogsActivity;

    public const DRAFT = 'brouillon';

    public const VALIDATED = 'validee';

    public const PAID = 'payee';

    protected function casts(): array
    {
        return ['situation_date' => 'date', 'market_amount' => 'integer', 'number' => 'integer', 'validated_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('situations')
            ->logOnly(['contract_id', 'number', 'situation_date', 'progress_cumul', 'market_amount', 'retention_rate', 'advance_refund_rate', 'vat_rate', 'status'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<SituationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SituationLine::class);
    }

    /** @return HasMany<SituationPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SituationPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /** @return array{cumul: int, previousCumul: int, amount: int, retention: int, advanceRefund: int, netHt: int, vat: int, netTtc: int} */
    public function computation(): array
    {
        return SituationCalculator::compute(
            $this->market_amount,
            (float) $this->progress_cumul,
            (float) $this->previous_progress,
            (float) $this->retention_rate,
            (float) $this->advance_refund_rate,
            (float) $this->vat_rate,
        );
    }

    /** Somme des paiements reçus. */
    public function paidAmount(): int
    {
        return $this->payments->reduce(fn (int $sum, SituationPayment $p): int => $sum + $p->amount, 0);
    }
}
