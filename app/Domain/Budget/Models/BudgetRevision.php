<?php

declare(strict_types=1);

namespace App\Domain\Budget\Models;

use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Révision du budget d'un poste (RG10.2). En attente, elle ne change rien ;
 * validée par la Direction, elle modifie le budget révisé du poste.
 *
 * @property int $id
 * @property int $chantier_id
 * @property int $budget_line_id
 * @property int $amount
 * @property string $reason
 * @property int $requested_by
 * @property string $status
 * @property int|null $decided_by
 * @property CarbonInterface|null $decided_at
 * @property string|null $decision_reason
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['chantier_id', 'budget_line_id', 'amount', 'reason', 'requested_by', 'status', 'decided_by', 'decided_at', 'decision_reason'])]
class BudgetRevision extends Model
{
    use LogsActivity;

    public const PENDING = 'en_attente';

    public const VALIDATED = 'validee';

    public const REJECTED = 'rejetee';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'decided_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('budget')
            ->logOnly(['chantier_id', 'budget_line_id', 'amount', 'reason', 'requested_by', 'status', 'decided_by', 'decision_reason'])
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

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
