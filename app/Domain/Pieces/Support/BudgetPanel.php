<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Support;

use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\PieceLine;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\RubriqueBudget;

/**
 * Panneau budget d'une rubrique pour un exercice, calculé côté serveur :
 * montant du budget, consommé (pièces approuvées) + en cours (saisies et en validation),
 * budget restant et taux de consommation.
 */
final class BudgetPanel
{
    /**
     * @param  int|null  $excludingPieceId  pièce en cours de saisie : ses lignes sont comptées à part
     * @return array{rubrique: array{id: int, code: string, label: string}, budget: int, consumed: int, inProgress: int, used: int, remaining: int, rate: float, overrun: bool}
     */
    public function for(Rubrique $rubrique, int $fiscalYearId, ?int $excludingPieceId = null): array
    {
        $budget = RubriqueBudget::query()
            ->where('rubrique_id', $rubrique->id)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();
        $budget = $budget instanceof RubriqueBudget ? $budget->amount : 0;

        $consumed = $this->sum($rubrique, $fiscalYearId, [PieceStatus::Approuvee], $excludingPieceId);
        $inProgress = $this->sum($rubrique, $fiscalYearId, [PieceStatus::Saisie, PieceStatus::EnValidation], $excludingPieceId);
        $used = $consumed + $inProgress;

        return [
            'rubrique' => ['id' => $rubrique->id, 'code' => $rubrique->code, 'label' => $rubrique->label],
            'budget' => $budget,
            'consumed' => $consumed,
            'inProgress' => $inProgress,
            'used' => $used,
            'remaining' => $budget - $used,
            'rate' => $budget > 0 ? round($used / $budget * 100, 1) : 0.0,
            'overrun' => $used > $budget,
        ];
    }

    /**
     * @param  list<PieceStatus>  $statuses
     */
    private function sum(Rubrique $rubrique, int $fiscalYearId, array $statuses, ?int $excludingPieceId): int
    {
        return (int) PieceLine::query()
            ->where('rubrique_id', $rubrique->id)
            ->whereHas('piece', fn ($q) => $q
                ->where('fiscal_year_id', $fiscalYearId)
                ->whereIn('status', array_map(fn (PieceStatus $s): string => $s->value, $statuses))
                ->when($excludingPieceId !== null, fn ($w) => $w->where('id', '!=', $excludingPieceId)))
            ->sum('amount');
    }

    /** Pièce qui dépasse le budget d'au moins une de ses rubriques (tous les montants en cours inclus). */
    public function overrunRubriques(Piece $piece): int
    {
        $count = 0;

        foreach ($piece->lines->map(fn (PieceLine $l): int => $l->rubrique_id)->unique() as $rubriqueId) {
            $rubrique = Rubrique::query()->find($rubriqueId);

            if ($rubrique !== null && $this->for($rubrique, $piece->fiscal_year_id)['overrun']) {
                $count++;
            }
        }

        return $count;
    }
}
