<?php

declare(strict_types=1);

namespace App\Domain\Budget\Models;

use App\Domain\Chantiers\Models\Chantier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Engagement sur le budget d'un chantier (RG10.1) : créé à la validation d'une
 * demande d'achat, soldé à la réception de la commande (V2) puis basculé en
 * coût réel, sans jamais compter deux fois le même montant.
 *
 * @property int $id
 * @property int $chantier_id
 * @property int $budget_line_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $amount
 * @property string $status
 * @property CarbonInterface|null $settled_at
 */
#[Fillable(['chantier_id', 'budget_line_id', 'source_type', 'source_id', 'amount', 'status', 'settled_at'])]
class BudgetCommitment extends Model
{
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SETTLED = 'settled';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'settled_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('budget')
            ->logOnly(['chantier_id', 'budget_line_id', 'source_type', 'source_id', 'amount', 'status'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return BelongsTo<BudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
