<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Achats\Policies\PurchaseRequestPolicy;
use App\Domain\Achats\States\RequestState;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\ModelStates\HasStates;

/**
 * Demande d'achat (M8.1) : liste d'articles et de quantités pour un chantier,
 * valorisée aux prix du référentiel. Jamais supprimée : annulée par son auteur.
 *
 * @property int $id
 * @property string $number
 * @property int $chantier_id
 * @property int|null $budget_line_id
 * @property int $requester_id
 * @property string|null $justification
 * @property RequestState $status
 * @property bool $needs_direction
 * @property int $total_amount
 * @property int|null $decided_by
 * @property CarbonInterface|null $decided_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['number', 'chantier_id', 'budget_line_id', 'requester_id', 'justification', 'needs_direction', 'total_amount', 'decided_by', 'decided_at'])]
#[UsePolicy(PurchaseRequestPolicy::class)]
#[UseFactory(PurchaseRequestFactory::class)]
class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory, HasStates, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => RequestState::class,
            'needs_direction' => 'boolean',
            'total_amount' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['number', 'chantier_id', 'budget_line_id', 'requester_id', 'justification', 'status', 'needs_direction', 'total_amount', 'decided_by'])
            ->logOnlyDirty();
    }

    /**
     * Montant courant : prix figés après validation (RG8.6), sinon prix du
     * référentiel ; quantités ajustées par le valideur si elles existent (RG8.5).
     */
    public function computeTotal(): int
    {
        return $this->lines->sum(fn (PurchaseRequestLine $l): int => $l->amount());
    }

    /** Montant des quantités initialement demandées, aux mêmes prix. */
    public function requestedTotal(): int
    {
        return $this->lines->sum(fn (PurchaseRequestLine $l): int => $l->amount(requested: true));
    }

    /** Chantier de la demande. */
    public function site(): Chantier
    {
        $chantier = $this->chantier;
        assert($chantier instanceof Chantier);

        return $chantier;
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
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return HasMany<PurchaseRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class)->orderBy('id');
    }

    /** @return HasMany<PurchaseRequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PurchaseRequestEvent::class)->orderBy('id');
    }
}
