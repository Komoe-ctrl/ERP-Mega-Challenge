<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Actions;

use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;

/**
 * Enregistre le tarif d'un fournisseur pour un article (RG3.2). Un tarif
 * existant est mis à jour, jamais dupliqué.
 */
final class SetSupplierTariff
{
    public function execute(Supplier $supplier, int $articleId, int $price): SupplierTariff
    {
        return SupplierTariff::query()->updateOrCreate(
            ['supplier_id' => $supplier->id, 'article_id' => $articleId],
            ['price' => $price],
        );
    }
}
