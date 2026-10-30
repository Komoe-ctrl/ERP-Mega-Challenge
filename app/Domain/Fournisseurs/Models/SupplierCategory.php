<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catégorie d'un fournisseur (RG3.1).
 *
 * @property int $id
 * @property int $supplier_id
 * @property string $category
 */
#[Fillable(['supplier_id', 'category'])]
class SupplierCategory extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
