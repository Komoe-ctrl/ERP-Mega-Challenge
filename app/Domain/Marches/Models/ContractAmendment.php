<?php

declare(strict_types=1);

namespace App\Domain\Marches\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Avenant d'un marché : sans effet tant qu'il n'est pas validé (RG6.2).
 *
 * @property int $id
 * @property int $contract_id
 * @property string $number
 * @property CarbonInterface $amendment_date
 * @property string $label
 * @property int $amount
 * @property int $days
 * @property bool $is_validated
 * @property int|null $created_by
 * @property int|null $validated_by
 * @property CarbonInterface|null $validated_at
 */
#[Fillable(['contract_id', 'number', 'amendment_date', 'label', 'amount', 'days', 'is_validated', 'created_by', 'validated_by', 'validated_at'])]
class ContractAmendment extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['amendment_date' => 'date', 'amount' => 'integer', 'days' => 'integer', 'is_validated' => 'boolean', 'validated_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('marches')
            ->logOnly(['contract_id', 'number', 'amendment_date', 'label', 'amount', 'days', 'is_validated'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
