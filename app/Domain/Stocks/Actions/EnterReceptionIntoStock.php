<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Actions;

use App\Domain\Achats\Models\PurchaseOrderLine;
use App\Domain\Achats\Models\Reception;
use App\Domain\Stocks\Enums\MovementType;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Domain\Stocks\Support\StockLedger;
use App\Models\User;

/**
 * Entrée en stock des quantités acceptées d'une réception, dans le magasin du chantier, au prix de la
 * commande (coût moyen pondéré, RG9.5). Le coût est déjà imputé au chantier à la réception (RG10.1) :
 * les sorties de ce magasin ne le comptent donc pas une seconde fois.
 */
final class EnterReceptionIntoStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function line(Reception $reception, PurchaseOrderLine $line, float $accepted, User $by): ?StockMovement
    {
        $article = $line->article;
        $chantier = $reception->order?->chantier;

        if ($article === null || $chantier === null || $accepted <= 0) {
            return null;
        }

        $warehouse = Warehouse::forChantier($chantier);
        $this->ledger->credit($warehouse, $article, $accepted, $line->unit_price);

        return StockMovement::query()->create([
            'type' => MovementType::Entree,
            'warehouse_id' => $warehouse->id,
            'article_id' => $article->id,
            'quantity' => $accepted,
            'direction' => 'in',
            'unit_cost' => $line->unit_price,
            'chantier_id' => $chantier->id,
            'source_type' => $reception->getMorphClass(),
            'source_id' => $reception->id,
            'status' => StockMovement::VALID,
            'created_by' => $by->id,
        ]);
    }
}
