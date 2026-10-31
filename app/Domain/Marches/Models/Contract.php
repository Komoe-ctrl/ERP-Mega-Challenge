<?php

declare(strict_types=1);

namespace App\Domain\Marches\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Devis\Models\Quote;
use App\Domain\Marches\Policies\ContractPolicy;
use App\Domain\Projets\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Marché (M6) : contrat signé avec un client, issu d'un devis validé.
 *
 * @property int $id
 * @property string $number
 * @property int|null $quote_id
 * @property int $client_id
 * @property int $project_id
 * @property CarbonInterface $signed_at
 * @property int $initial_amount
 * @property int $deadline_days
 * @property string $advance_rate
 * @property string $retention_rate
 * @property int $payment_days
 * @property int|null $created_by
 */
#[Fillable(['number', 'quote_id', 'client_id', 'project_id', 'signed_at', 'initial_amount', 'deadline_days', 'advance_rate', 'retention_rate', 'payment_days', 'created_by'])]
#[UsePolicy(ContractPolicy::class)]
class Contract extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['signed_at' => 'date', 'initial_amount' => 'integer', 'deadline_days' => 'integer', 'payment_days' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('marches')
            ->logOnly(['number', 'quote_id', 'client_id', 'project_id', 'signed_at', 'initial_amount', 'deadline_days', 'advance_rate', 'retention_rate', 'payment_days'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
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

    /** @return HasMany<ContractAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class)->orderBy('id');
    }

    /**
     * RG6.2 : montant actualisé = montant initial + somme des avenants validés ; délai = initial + jours des avenants validés.
     *
     * @return array{amendmentsAmount: int, updatedAmount: int, contractualDays: int}
     */
    public function totals(): array
    {
        $validated = $this->amendments->where('is_validated', true);
        $amount = $validated->reduce(fn (int $sum, ContractAmendment $a): int => $sum + $a->amount, 0);
        $days = $validated->reduce(fn (int $sum, ContractAmendment $a): int => $sum + $a->days, 0);

        return [
            'amendmentsAmount' => $amount,
            'updatedAmount' => $this->initial_amount + $amount,
            'contractualDays' => $this->deadline_days + $days,
        ];
    }
}
