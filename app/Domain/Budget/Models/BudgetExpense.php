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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Dépense diverse d'un chantier (RG10.4) : imputée à un poste, avec justificatif obligatoire.
 *
 * @property int $id
 * @property int $chantier_id
 * @property int $budget_line_id
 * @property string $label
 * @property int $amount
 * @property CarbonInterface $spent_on
 * @property int|null $user_id
 */
#[Fillable(['chantier_id', 'budget_line_id', 'label', 'amount', 'spent_on', 'user_id'])]
class BudgetExpense extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_on' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('budget')
            ->logOnly(['chantier_id', 'budget_line_id', 'label', 'amount', 'spent_on', 'user_id'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts')->singleFile();
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
