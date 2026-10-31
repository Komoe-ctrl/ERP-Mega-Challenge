<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Support;

use App\Domain\Achats\Models\Article;
use App\Domain\Stocks\Models\StockLevel;
use App\Domain\Stocks\Models\Warehouse;
use Illuminate\Validation\ValidationException;

/**
 * Grand livre des quantités : crédite et débite le stock d'un article dans un magasin sous verrou de
 * ligne, maintient le coût moyen pondéré (RG9.5) et refuse un stock négatif.
 * À appeler dans une transaction.
 */
final class StockLedger
{
    private const EPSILON = 0.0005;

    /**
     * Entrée en stock : la quantité augmente, le coût moyen pondéré est recalculé.
     */
    public function credit(Warehouse $warehouse, Article $article, float $quantity, int $unitCost): StockLevel
    {
        $level = $this->level($warehouse, $article);
        $current = $level->qty();
        $total = $current + $quantity;

        $average = $total > self::EPSILON
            ? (int) round(($current * $level->average_cost + $quantity * $unitCost) / $total)
            : $unitCost;

        $level->update(['quantity' => round($total, 3), 'average_cost' => $average]);

        return $level;
    }

    /**
     * Sortie du stock au coût moyen pondéré courant ; échoue si le stock est insuffisant.
     */
    public function debit(Warehouse $warehouse, Article $article, float $quantity): StockLevel
    {
        $level = $this->level($warehouse, $article);

        if ($quantity > $level->qty() + self::EPSILON) {
            throw ValidationException::withMessages([
                'quantity' => "Stock insuffisant pour « {$article->label} » dans {$warehouse->name} : {$this->show($level->qty())} disponible(s).",
            ]);
        }

        $level->update(['quantity' => round(max(0.0, $level->qty() - $quantity), 3)]);

        return $level;
    }

    /** Coût moyen pondéré courant de l'article dans le magasin (0 sans stock). */
    public function averageCost(Warehouse $warehouse, Article $article): int
    {
        $level = StockLevel::query()->where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->first();

        return $level instanceof StockLevel ? $level->average_cost : 0;
    }

    private function level(Warehouse $warehouse, Article $article): StockLevel
    {
        $level = StockLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('article_id', $article->id)
            ->lockForUpdate()
            ->first();

        return $level ?? StockLevel::query()->create([
            'warehouse_id' => $warehouse->id,
            'article_id' => $article->id,
            'quantity' => 0,
            'average_cost' => 0,
            'minimum' => 0,
        ]);
    }

    private function show(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }
}
