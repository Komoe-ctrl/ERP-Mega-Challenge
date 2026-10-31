<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Models;

use App\Domain\Achats\Models\Article;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'inventaire : stock théorique (figé), stock compté, écart.
 *
 * @property int $id
 * @property int $inventory_id
 * @property int $article_id
 * @property string $theoretical
 * @property string|null $counted
 */
#[Fillable(['inventory_id', 'article_id', 'theoretical', 'counted'])]
class InventoryLine extends Model
{
    /** Écart = compté − théorique ; nul tant que la ligne n'est pas comptée. */
    public function gap(): float
    {
        return $this->counted === null ? 0.0 : (float) $this->counted - (float) $this->theoretical;
    }

    /** @return BelongsTo<Inventory, $this> */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
