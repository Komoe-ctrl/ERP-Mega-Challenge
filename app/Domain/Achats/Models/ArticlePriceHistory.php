<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'historique d'un prix (RG8.13) : jamais modifiée.
 *
 * @property int $id
 * @property int $article_id
 * @property int $price
 * @property int|null $user_id
 * @property string $reason
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['article_id', 'price', 'user_id', 'reason'])]
class ArticlePriceHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
