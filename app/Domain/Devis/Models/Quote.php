<?php

declare(strict_types=1);

namespace App\Domain\Devis\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Devis\Policies\QuotePolicy;
use App\Domain\Devis\States\QuoteState;
use App\Domain\Devis\Support\QuoteTotals;
use App\Domain\Projets\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\ModelStates\HasStates;

/**
 * Devis (M5). Chaque version est une ligne : le numéro est commun, la version s'incrémente (RG5.3).
 *
 * @property int $id
 * @property string $number
 * @property int $version
 * @property int $client_id
 * @property int $project_id
 * @property CarbonInterface $quote_date
 * @property QuoteState $status
 * @property string $discount_rate
 * @property string $vat_rate
 * @property int $direct_cost
 * @property string|null $refusal_reason
 * @property int|null $created_by
 * @property int|null $decided_by
 * @property CarbonInterface|null $decided_at
 */
#[Fillable(['number', 'version', 'client_id', 'project_id', 'quote_date', 'discount_rate', 'vat_rate', 'direct_cost', 'refusal_reason', 'created_by', 'decided_by', 'decided_at'])]
#[UsePolicy(QuotePolicy::class)]
class Quote extends Model
{
    use HasStates, LogsActivity;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'quote_date' => 'date',
            'direct_cost' => 'integer',
            'decided_at' => 'datetime',
            'status' => QuoteState::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('devis')
            ->logOnly(['number', 'version', 'client_id', 'project_id', 'quote_date', 'status', 'discount_rate', 'vat_rate', 'direct_cost', 'refusal_reason'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuoteWork, $this> */
    public function works(): HasMany
    {
        return $this->hasMany(QuoteWork::class)->orderBy('position')->orderBy('id');
    }

    /** @return array{subtotal: int, discount: int, ht: int, vat: int, ttc: int, margin: int, marginRate: float} */
    public function totals(): array
    {
        return QuoteTotals::of($this);
    }
}
