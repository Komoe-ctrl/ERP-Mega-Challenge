<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Achats\Policies\ArticlePolicy;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Article du référentiel des prix (RG8.12). Prix de référence interne : le
 * demandeur ne saisit jamais de prix. Jamais supprimé (RG8.14).
 *
 * @property int $id
 * @property string $reference
 * @property string $label
 * @property string $category
 * @property string $unit
 * @property int $price
 * @property bool $is_active
 */
#[Fillable(['reference', 'label', 'category', 'unit', 'price', 'is_active'])]
#[UsePolicy(ArticlePolicy::class)]
#[UseFactory(ArticleFactory::class)]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, LogsActivity;

    public const CATEGORIES = [
        'Ciment', 'Fer', 'Sable', 'Gravier', 'Bois', 'Peinture', 'Plomberie',
        'Électricité', 'Quincaillerie', 'Maçonnerie', 'Équipements',
    ];

    public const UNITS = [
        'sac', 'barre', 'kg', 'm³', 'pièce', 'panneau', 'seau', 'pot', 'boîte',
        'couronne', 'unité', 'tonne',
    ];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['reference', 'label', 'category', 'unit', 'price', 'is_active'])
            ->logOnlyDirty();
    }

    /** @return HasMany<ArticlePriceHistory, $this> */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ArticlePriceHistory::class)->orderBy('id');
    }
}
