<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Models;

use App\Domain\Achats\Models\Reception;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Évaluation d'un fournisseur après une réception (RG3.3) : délais de
 * livraison, qualité et respect des prix, notés de 1 à 5.
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $reception_id
 * @property int $delay_score
 * @property int $quality_score
 * @property int $price_score
 * @property string|null $comment
 * @property int|null $user_id
 */
#[Fillable(['supplier_id', 'reception_id', 'delay_score', 'quality_score', 'price_score', 'comment', 'user_id'])]
class SupplierEvaluation extends Model
{
    use LogsActivity;

    /** Note moyenne de cette évaluation, sur 5. */
    public function average(): float
    {
        return ($this->delay_score + $this->quality_score + $this->price_score) / 3;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fournisseurs')
            ->logOnly(['supplier_id', 'reception_id', 'delay_score', 'quality_score', 'price_score', 'comment'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Reception, $this> */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
