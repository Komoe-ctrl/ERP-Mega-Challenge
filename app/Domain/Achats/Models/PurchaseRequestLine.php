<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une demande d'achat (RG8.1) : article, quantité, date de besoin, urgence.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int $article_id
 * @property string $quantity
 * @property string|null $adjusted_quantity
 * @property CarbonInterface $need_date
 * @property string $urgency
 * @property int|null $frozen_price
 */
#[Fillable(['purchase_request_id', 'article_id', 'quantity', 'adjusted_quantity', 'need_date', 'urgency', 'frozen_price'])]
class PurchaseRequestLine extends Model
{
    public const URGENCIES = ['normale', 'urgente', 'critique'];

    protected function casts(): array
    {
        return ['need_date' => 'date', 'frozen_price' => 'integer'];
    }

    /** Prix unitaire : figé à la validation, sinon prix en vigueur du référentiel (RG8.2, RG8.6). */
    public function unitPrice(): int
    {
        return $this->frozen_price ?? $this->item()->price;
    }

    /** Quantité retenue : ajustée par le valideur (RG8.5) ou demandée. */
    public function effectiveQuantity(bool $requested = false): float
    {
        return (float) (! $requested && $this->adjusted_quantity !== null ? $this->adjusted_quantity : $this->quantity);
    }

    /** Montant de la ligne en FCFA entiers (arrondi au franc). */
    public function amount(bool $requested = false): int
    {
        return (int) round($this->unitPrice() * $this->effectiveQuantity($requested));
    }

    /** Article du référentiel visé par la ligne. */
    public function item(): Article
    {
        $article = $this->article;
        assert($article instanceof Article);

        return $article;
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
