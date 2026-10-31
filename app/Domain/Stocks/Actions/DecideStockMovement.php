<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Actions;

use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validation ou rejet d'une perte ou d'un ajustement en attente (RG9.3). Le valideur n'est jamais
 * l'auteur du mouvement ; la décision est tracée dans le journal des activités.
 */
final class DecideStockMovement
{
    public function __construct(private readonly RecordStockMovement $recorder) {}

    public function validate(StockMovement $movement, User $by): StockMovement
    {
        return DB::transaction(function () use ($movement, $by): StockMovement {
            $movement = $this->lock($movement);
            $this->guard($movement, $by);

            $warehouse = Warehouse::query()->findOrFail($movement->warehouse_id);
            $this->recorder->apply($movement, $warehouse, $movement->article()->firstOrFail());

            $movement->update(['status' => StockMovement::VALID, 'validated_by' => $by->id, 'validated_at' => now()]);

            return $movement->refresh();
        });
    }

    public function reject(StockMovement $movement, User $by, string $reason): StockMovement
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif du rejet.']);
        }

        return DB::transaction(function () use ($movement, $by, $reason): StockMovement {
            $movement = $this->lock($movement);
            $this->guard($movement, $by);

            $movement->update([
                'status' => StockMovement::REJECTED,
                'validated_by' => $by->id,
                'validated_at' => now(),
                'reason' => trim(($movement->reason ?? '').' — rejeté : '.trim($reason)),
            ]);

            return $movement->refresh();
        });
    }

    private function lock(StockMovement $movement): StockMovement
    {
        return StockMovement::query()->lockForUpdate()->findOrFail($movement->id);
    }

    private function guard(StockMovement $movement, User $by): void
    {
        if ($movement->status !== StockMovement::PENDING) {
            throw ValidationException::withMessages(['movement' => 'Ce mouvement a déjà reçu une décision.']);
        }

        if ($movement->created_by === $by->id) {
            throw ValidationException::withMessages(['movement' => 'Vous ne pouvez pas valider votre propre mouvement (séparation des tâches).']);
        }
    }
}
