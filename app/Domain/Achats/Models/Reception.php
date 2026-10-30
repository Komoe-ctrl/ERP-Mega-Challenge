<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Fournisseurs\Models\SupplierEvaluation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Réception d'une livraison (RG8.9) : conforme, avec reliquat ou avec retour.
 *
 * @property int $id
 * @property string $number
 * @property int $purchase_order_id
 * @property CarbonInterface $received_on
 * @property string $gap
 * @property string|null $note
 * @property int|null $received_by
 */
#[Fillable(['number', 'purchase_order_id', 'received_on', 'gap', 'note', 'received_by'])]
class Reception extends Model
{
    use LogsActivity;

    public const CONFORM = 'conforme';

    public const BALANCE = 'reliquat';

    public const RETURNED = 'retour';

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['number', 'purchase_order_id', 'received_on', 'gap', 'received_by'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasOne<SupplierEvaluation, $this> */
    public function evaluation(): HasOne
    {
        return $this->hasOne(SupplierEvaluation::class);
    }

    /** @return HasMany<ReceptionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceptionLine::class)->orderBy('id');
    }
}
