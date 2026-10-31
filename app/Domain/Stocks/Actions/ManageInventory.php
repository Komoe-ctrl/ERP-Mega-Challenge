<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Actions;

use App\Domain\Shared\Support\DocumentNumbers;
use App\Domain\Stocks\Enums\MovementType;
use App\Domain\Stocks\Models\Inventory;
use App\Domain\Stocks\Models\InventoryLine;
use App\Domain\Stocks\Models\StockLevel;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventaire d'un magasin (CA9) : ouverture (stock théorique figé), saisie des quantités comptées,
 * validation par un tiers qui ajuste le stock sur les écarts constatés.
 */
final class ManageInventory
{
    public function __construct(private readonly RecordStockMovement $recorder) {}

    public function open(Warehouse $warehouse, User $by): Inventory
    {
        return DB::transaction(function () use ($warehouse, $by): Inventory {
            if (Inventory::query()->where('warehouse_id', $warehouse->id)->where('status', Inventory::OPEN)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['warehouse_id' => 'Un inventaire est déjà ouvert pour ce magasin.']);
            }

            $inventory = Inventory::query()->create([
                'number' => DocumentNumbers::next('inventaire', 'INV-{YYYY}-{SEQ:4}'),
                'warehouse_id' => $warehouse->id,
                'status' => Inventory::OPEN,
                'opened_by' => $by->id,
            ]);

            foreach (StockLevel::query()->where('warehouse_id', $warehouse->id)->get() as $level) {
                $inventory->lines()->create(['article_id' => $level->article_id, 'theoretical' => $level->quantity]);
            }

            return $inventory->load('lines.article');
        });
    }

    /**
     * @param  array<int, float|int|string|null>  $counted  quantité comptée par id de ligne d'inventaire
     */
    public function count(Inventory $inventory, array $counted): Inventory
    {
        if ($inventory->status !== Inventory::OPEN) {
            throw ValidationException::withMessages(['inventory' => 'Cet inventaire est déjà validé.']);
        }

        DB::transaction(function () use ($inventory, $counted): void {
            foreach ($counted as $lineId => $quantity) {
                if ($quantity === null || $quantity === '') {
                    continue;
                }

                if (! is_numeric($quantity) || (float) $quantity < 0) {
                    throw ValidationException::withMessages(['counted' => 'Les quantités comptées sont des nombres positifs ou nuls.']);
                }

                InventoryLine::query()->where('inventory_id', $inventory->id)->whereKey($lineId)->update(['counted' => round((float) $quantity, 3)]);
            }
        });

        return $inventory->refresh()->load('lines.article');
    }

    /**
     * Valide l'inventaire : le stock de chaque article compté est ramené à la quantité comptée, par un
     * mouvement « inventaire » tracé. Le valideur n'est jamais celui qui a ouvert l'inventaire.
     */
    public function validate(Inventory $inventory, User $by): Inventory
    {
        return DB::transaction(function () use ($inventory, $by): Inventory {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);

            if ($inventory->status !== Inventory::OPEN) {
                throw ValidationException::withMessages(['inventory' => 'Cet inventaire est déjà validé.']);
            }

            if ($inventory->opened_by === $by->id) {
                throw ValidationException::withMessages(['inventory' => 'Vous ne pouvez pas valider l\'inventaire que vous avez ouvert (séparation des tâches).']);
            }

            $warehouse = Warehouse::query()->findOrFail($inventory->warehouse_id);
            $lines = $inventory->lines()->whereNotNull('counted')->with('article')->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['inventory' => 'Saisissez au moins une quantité comptée.']);
            }

            foreach ($lines as $line) {
                $article = $line->article;
                $level = StockLevel::query()->where('warehouse_id', $warehouse->id)->where('article_id', $line->article_id)->lockForUpdate()->first();
                $current = $level instanceof StockLevel ? $level->qty() : 0.0;
                $delta = round((float) $line->counted - $current, 3);

                if ($article === null || abs($delta) < 0.0005) {
                    continue;
                }

                $movement = StockMovement::query()->create([
                    'type' => MovementType::Inventaire,
                    'warehouse_id' => $warehouse->id,
                    'article_id' => $line->article_id,
                    'quantity' => abs($delta),
                    'direction' => $delta > 0 ? 'in' : 'out',
                    'unit_cost' => 0,
                    'source_type' => $inventory->getMorphClass(),
                    'source_id' => $inventory->id,
                    'reason' => "Inventaire {$inventory->number} : compté {$line->counted}, théorique {$line->theoretical}",
                    'status' => StockMovement::VALID,
                    'created_by' => $by->id,
                    'validated_by' => $by->id,
                    'validated_at' => now(),
                ]);

                $this->recorder->apply($movement, $warehouse, $article);
            }

            $inventory->update(['status' => Inventory::VALIDATED, 'validated_by' => $by->id, 'validated_at' => now()]);

            return $inventory->load('lines.article');
        });
    }
}
