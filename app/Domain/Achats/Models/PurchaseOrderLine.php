<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'un bon de commande, rattachée à la ligne de demande qu'elle sert.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $purchase_request_line_id
 * @property int $article_id
 * @property string $quantity
 * @property int $unit_price
 * @property string $received_quantity
 */
#[Fillable(['purchase_order_id', 'purchase_request_line_id', 'article_id', 'quantity', 'unit_price', 'received_quantity'])]
class PurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return ['unit_price' => 'integer'];
    }

    /** Montant de la ligne commandée, en FCFA entiers. */
    public function amount(): int
    {
        return (int) round($this->unit_price * (float) $this->quantity);
    }

    /** Quantité restant à recevoir (reliquat). */
    public function remaining(): float
    {
        return max(0.0, (float) $this->quantity - (float) $this->received_quantity);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<PurchaseRequestLine, $this> */
    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'purchase_request_line_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
