<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Models;

use App\Domain\Achats\Models\Article;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tarif d'un fournisseur pour un article (RG3.2), en FCFA entiers.
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $article_id
 * @property int $price
 */
#[Fillable(['supplier_id', 'article_id', 'price'])]
class SupplierTariff extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fournisseurs')
            ->logOnly(['supplier_id', 'article_id', 'price'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
