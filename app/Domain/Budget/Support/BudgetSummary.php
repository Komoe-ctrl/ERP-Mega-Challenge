<?php

declare(strict_types=1);

namespace App\Domain\Budget\Support;

use App\Domain\Budget\Models\BudgetLine;
use Illuminate\Support\Collection;

/**
 * Indicateurs budgétaires d'un chantier calculés depuis ses postes (M10).
 * Reste à engager = budget révisé − engagements − coût réel.
 */
final readonly class BudgetSummary
{
    public function __construct(
        public int $initial,
        public int $revised,
        public int $committed,
        public int $actual,
    ) {}

    /** @param Collection<int, BudgetLine> $lines */
    public static function fromLines(Collection $lines): self
    {
        return new self(
            initial: $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->initial_amount, 0),
            revised: $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->revised_amount, 0),
            committed: $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->committed_amount, 0),
            actual: $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->actual_amount, 0),
        );
    }

    public function remaining(): int
    {
        return $this->revised - $this->committed - $this->actual;
    }

    /** Taux de consommation du budget révisé, en pourcentage (engagé + réel). */
    public function consumption(): float
    {
        return $this->revised === 0 ? 0.0 : (($this->committed + $this->actual) / $this->revised) * 100;
    }

    /**
     * @return array{initial: int, revised: int, committed: int, actual: int, remaining: int, consumption: float}
     */
    public function toArray(): array
    {
        return [
            'initial' => $this->initial,
            'revised' => $this->revised,
            'committed' => $this->committed,
            'actual' => $this->actual,
            'remaining' => $this->remaining(),
            'consumption' => $this->consumption(),
        ];
    }
}
