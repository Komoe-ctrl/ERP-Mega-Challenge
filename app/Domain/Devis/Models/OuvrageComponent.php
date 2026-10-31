<?php

declare(strict_types=1);

namespace App\Domain\Devis\Models;

use App\Domain\Achats\Models\Article;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composant d'un ouvrage : un article du référentiel (prix de référence) ou une ligne libre (main-d'œuvre).
 *
 * @property int $id
 * @property int $ouvrage_id
 * @property int|null $article_id
 * @property string|null $label
 * @property string $quantity
 * @property int|null $unit_price
 */
#[Fillable(['ouvrage_id', 'article_id', 'label', 'quantity', 'unit_price'])]
class OuvrageComponent extends Model
{
    protected function casts(): array
    {
        return ['unit_price' => 'integer'];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function cost(): int
    {
        $price = $this->article !== null ? $this->article->price : (int) $this->unit_price;

        return (int) round($price * (float) $this->quantity);
    }
}
