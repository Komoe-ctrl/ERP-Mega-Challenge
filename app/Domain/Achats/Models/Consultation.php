<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Achats\Policies\ConsultationPolicy;
use App\Domain\Chantiers\Models\Chantier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Consultation de fournisseurs sur une demande validée (RG8.11) : offres,
 * comparatif, choix du fournisseur puis bon de commande.
 *
 * @property int $id
 * @property string $number
 * @property int $purchase_request_id
 * @property int $chantier_id
 * @property string $status
 * @property CarbonInterface $deadline
 * @property string|null $note
 * @property int $min_suppliers
 * @property int|null $created_by
 */
#[Fillable(['number', 'purchase_request_id', 'chantier_id', 'status', 'deadline', 'note', 'min_suppliers', 'created_by'])]
#[UsePolicy(ConsultationPolicy::class)]
class Consultation extends Model
{
    use LogsActivity;

    public const OPEN = 'ouverte';

    public const COMPARED = 'comparee';

    public const AWARDED = 'attribuee';

    protected function casts(): array
    {
        return ['deadline' => 'date', 'min_suppliers' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['number', 'purchase_request_id', 'chantier_id', 'status', 'deadline', 'min_suppliers'])
            ->logOnlyDirty();
    }

    /** Demande d'achat consultée. */
    public function demand(): PurchaseRequest
    {
        $request = $this->request;
        assert($request instanceof PurchaseRequest);

        return $request;
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return HasMany<ConsultationQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(ConsultationQuote::class)->orderBy('total_amount');
    }
}
