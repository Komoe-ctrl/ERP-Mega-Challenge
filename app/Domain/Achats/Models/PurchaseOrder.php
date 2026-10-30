<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Achats\Policies\PurchaseOrderPolicy;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Fournisseurs\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Bon de commande (RG8.8) : peut regrouper plusieurs demandes validées d'un
 * même chantier ; une demande peut être servie par plusieurs commandes.
 *
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int $chantier_id
 * @property int|null $consultation_id
 * @property string $status
 * @property CarbonInterface $ordered_on
 * @property int $amount
 * @property int|null $created_by
 */
#[Fillable(['number', 'supplier_id', 'chantier_id', 'consultation_id', 'status', 'ordered_on', 'amount', 'created_by'])]
#[UsePolicy(PurchaseOrderPolicy::class)]
class PurchaseOrder extends Model
{
    use LogsActivity;

    public const ISSUED = 'emis';

    public const PARTIAL = 'partiel';

    public const RECEIVED = 'receptionne';

    public const INVOICED = 'facture';

    public const PAID = 'paye';

    protected function casts(): array
    {
        return ['ordered_on' => 'date', 'amount' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['number', 'supplier_id', 'chantier_id', 'consultation_id', 'status', 'ordered_on', 'amount'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return HasMany<Reception, $this> */
    public function receptions(): HasMany
    {
        return $this->hasMany(Reception::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('id');
    }
}
