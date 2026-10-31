<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Models;

use App\Domain\Achats\Models\Article;
use App\Domain\Stocks\Policies\StockPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock d'un article dans un magasin : quantité, coût moyen pondéré (RG9.5), seuil minimal (RG9.4).
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $article_id
 * @property string $quantity
 * @property int $average_cost
 * @property string $minimum
 */
#[Fillable(['warehouse_id', 'article_id', 'quantity', 'average_cost', 'minimum'])]
#[UsePolicy(StockPolicy::class)]
class StockLevel extends Model
{
    protected function casts(): array
    {
        return ['average_cost' => 'integer'];
    }

    public function qty(): float
    {
        return (float) $this->quantity;
    }

    /** Valeur du stock au coût moyen pondéré. */
    public function value(): int
    {
        return (int) round($this->qty() * $this->average_cost);
    }

    /** RG9.4 : sous le seuil minimal, l'article est à réapprovisionner. */
    public function isLow(): bool
    {
        return (float) $this->minimum > 0 && $this->qty() <= (float) $this->minimum;
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
