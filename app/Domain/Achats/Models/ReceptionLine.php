<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une réception : quantité livrée, renvoyée au fournisseur et acceptée.
 *
 * @property int $id
 * @property int $reception_id
 * @property int $purchase_order_line_id
 * @property string $delivered_quantity
 * @property string $returned_quantity
 * @property string $accepted_quantity
 */
#[Fillable(['reception_id', 'purchase_order_line_id', 'delivered_quantity', 'returned_quantity', 'accepted_quantity'])]
class ReceptionLine extends Model
{
    /** @return BelongsTo<Reception, $this> */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
