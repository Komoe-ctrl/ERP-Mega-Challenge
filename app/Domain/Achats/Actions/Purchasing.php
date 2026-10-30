<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\PurchaseOrderLine;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\Models\PurchaseRequestLine;
use App\Domain\Administration\Models\Setting;
use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;

/**
 * Règles communes du processus d'achat : quantités encore commandables,
 * fournisseurs éligibles (RG3.1) et seuil de consultation (RG8.11).
 */
final class Purchasing
{
    public const DEFAULT_CONSULTATION_THRESHOLD = 2_000_000;

    public const DEFAULT_MIN_SUPPLIERS = 3;

    /** Quantité d'une ligne de demande validée qui n'est pas encore commandée (commandes partielles, RG8.8). */
    public static function orderable(PurchaseRequestLine $line): float
    {
        $ordered = (float) PurchaseOrderLine::query()->where('purchase_request_line_id', $line->id)->sum('quantity');

        return max(0.0, $line->effectiveQuantity() - $ordered);
    }

    /** Montant au-delà duquel une consultation est obligatoire avant commande (RG8.11). */
    public static function consultationThreshold(): int
    {
        $value = Setting::get('validation.consultation_threshold_fcfa', self::DEFAULT_CONSULTATION_THRESHOLD);

        return is_int($value) ? $value : self::DEFAULT_CONSULTATION_THRESHOLD;
    }

    /** Nombre minimal de fournisseurs consultés au-delà du seuil (3 par défaut, RG8.11). */
    public static function minimumSuppliers(): int
    {
        $value = Setting::get('validation.consultation_min_suppliers', self::DEFAULT_MIN_SUPPLIERS);

        return is_int($value) ? max(1, $value) : self::DEFAULT_MIN_SUPPLIERS;
    }

    /**
     * Fournisseurs actifs de la bonne catégorie pour les articles de la demande (RG3.1).
     *
     * @return Collection<int, Supplier>
     */
    public static function eligibleSuppliers(PurchaseRequest $request): Collection
    {
        $request->loadMissing('lines.article');
        $categories = $request->lines->map(fn (PurchaseRequestLine $l): string => $l->item()->supplierCategory())->unique()->values()->all();

        return Supplier::query()
            ->where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('category', $categories))
            ->orderBy('name')
            ->get();
    }
}
