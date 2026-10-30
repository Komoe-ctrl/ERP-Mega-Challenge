<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Achats\Policies\SupplierInvoicePolicy;
use App\Domain\Fournisseurs\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Facture fournisseur. Payable seulement si elle concorde avec le bon de
 * commande et la réception (contrôle à trois voies, RG8.10).
 *
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int $purchase_order_id
 * @property int|null $reception_id
 * @property CarbonInterface $invoice_date
 * @property CarbonInterface $due_date
 * @property int $amount
 * @property string $status
 * @property bool $match_order
 * @property bool $match_reception
 * @property bool $match_price
 * @property int|null $created_by
 * @property int|null $controlled_by
 * @property CarbonInterface|null $controlled_at
 * @property CarbonInterface|null $paid_on
 * @property int|null $paid_by
 */
#[Fillable(['number', 'supplier_id', 'purchase_order_id', 'reception_id', 'invoice_date', 'due_date', 'amount', 'status', 'match_order', 'match_reception', 'match_price', 'created_by', 'controlled_by', 'controlled_at', 'paid_on', 'paid_by'])]
#[UsePolicy(SupplierInvoicePolicy::class)]
class SupplierInvoice extends Model
{
    use LogsActivity;

    public const TO_CONTROL = 'a_controler';

    public const CONTROLLED = 'controlee';

    public const GAP = 'ecart';

    public const PAID = 'payee';

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'amount' => 'integer',
            'match_order' => 'boolean',
            'match_reception' => 'boolean',
            'match_price' => 'boolean',
            'controlled_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['number', 'supplier_id', 'purchase_order_id', 'reception_id', 'invoice_date', 'due_date', 'amount', 'status', 'match_order', 'match_reception', 'match_price', 'controlled_by', 'paid_on', 'paid_by'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<Reception, $this> */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }
}
