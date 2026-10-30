<?php

declare(strict_types=1);

namespace App\Domain\Budget\Models;

use App\Domain\Budget\Policies\BudgetLinePolicy;
use App\Domain\Chantiers\Models\Chantier;
use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Poste du déboursé sec d'un chantier (M10) : budget initial et révisé,
 * engagements et coût réel, par poste et par nature. Montants en FCFA.
 *
 * @property int $id
 * @property int $chantier_id
 * @property string $post
 * @property string $nature
 * @property int $initial_amount
 * @property int $revised_amount
 * @property int $committed_amount
 * @property int $actual_amount
 */
#[Fillable(['chantier_id', 'post', 'nature', 'initial_amount', 'revised_amount', 'committed_amount', 'actual_amount'])]
#[UsePolicy(BudgetLinePolicy::class)]
#[UseFactory(BudgetLineFactory::class)]
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory, LogsActivity;

    public const NATURES = ['materiaux', 'main_oeuvre', 'materiel'];

    protected function casts(): array
    {
        return [
            'initial_amount' => 'integer',
            'revised_amount' => 'integer',
            'committed_amount' => 'integer',
            'actual_amount' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('budget')
            ->logOnly(['chantier_id', 'post', 'nature', 'initial_amount', 'revised_amount', 'committed_amount', 'actual_amount'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }
}
