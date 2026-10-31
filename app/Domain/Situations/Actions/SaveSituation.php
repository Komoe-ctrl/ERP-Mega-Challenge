<?php

declare(strict_types=1);

namespace App\Domain\Situations\Actions;

use App\Domain\Devis\Models\QuoteWork;
use App\Domain\Marches\Models\Contract;
use App\Domain\Situations\Models\Situation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et édition d'une situation de travaux en brouillon (M11).
 *
 * RG11.1 : l'avancement cumulé est saisi globalement ou ouvrage par ouvrage (devis du marché) ; il ne
 * diminue jamais d'une situation à l'autre. RG11.2 : retenue de garantie et remboursement d'acompte
 * reprennent les taux du marché (RG6.3), modifiables en brouillon.
 */
final class SaveSituation
{
    /**
     * @param  array{situation_date: string, progress?: float|int|string|null, works?: array<int|string, float|int|string>|null}  $data
     */
    public function create(Contract $contract, array $data, User $by): Situation
    {
        return DB::transaction(function () use ($contract, $data, $by): Situation {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $previous = Situation::query()->where('contract_id', $contract->id)->orderByDesc('number')->first();

            if ($previous !== null && $previous->isDraft()) {
                throw ValidationException::withMessages(['contract_id' => "La situation n°{$previous->number} est encore en brouillon : validez-la avant d'en créer une nouvelle."]);
            }

            $previousProgress = $previous !== null ? (float) $previous->progress_cumul : 0.0;
            $progress = $this->resolveProgress($contract, $data, $previousProgress);

            $situation = Situation::query()->create([
                'contract_id' => $contract->id,
                'number' => ($previous->number ?? 0) + 1,
                'situation_date' => $data['situation_date'],
                'progress_cumul' => $progress,
                'previous_progress' => $previousProgress,
                'market_amount' => $contract->load('amendments')->totals()['updatedAmount'],
                'retention_rate' => $contract->retention_rate,
                'advance_refund_rate' => $contract->advance_rate,
                'vat_rate' => 18,
                'status' => Situation::DRAFT,
                'created_by' => $by->id,
            ]);

            $this->saveLines($situation, $data['works'] ?? null);

            return $situation;
        });
    }

    /**
     * @param  array{situation_date: string, progress?: float|int|string|null, works?: array<int|string, float|int|string>|null, retention_rate: float|int|string, advance_refund_rate: float|int|string, vat_rate: float|int|string}  $data
     */
    public function update(Situation $situation, array $data): Situation
    {
        if (! $situation->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Une situation validée est figée : une correction passe par la situation suivante (RG11.3).']);
        }

        return DB::transaction(function () use ($situation, $data): Situation {
            $contract = Contract::query()->findOrFail($situation->contract_id);
            $progress = $this->resolveProgress($contract, $data, (float) $situation->previous_progress);

            $situation->update([
                'situation_date' => $data['situation_date'],
                'progress_cumul' => $progress,
                'retention_rate' => $data['retention_rate'],
                'advance_refund_rate' => $data['advance_refund_rate'],
                'vat_rate' => $data['vat_rate'],
            ]);

            $this->saveLines($situation, $data['works'] ?? null);

            return $situation;
        });
    }

    /**
     * Avancement cumulé en % : déduit des ouvrages (pondéré par leur montant) s'ils sont fournis, sinon global.
     *
     * @param  array{progress?: float|int|string|null, works?: array<int|string, float|int|string>|null}  $data
     */
    private function resolveProgress(Contract $contract, array $data, float $previousProgress): float
    {
        $works = $data['works'] ?? null;

        if (is_array($works) && $works !== []) {
            $progress = $this->weightedProgress($contract, $works);
        } elseif (isset($data['progress'])) {
            $progress = round((float) $data['progress'], 2);
        } else {
            throw ValidationException::withMessages(['progress' => 'Indiquez l\'avancement cumulé.']);
        }

        if ($progress > 100) {
            throw ValidationException::withMessages(['progress' => 'L\'avancement cumulé ne peut pas dépasser 100 %.']);
        }

        if ($progress < $previousProgress) {
            throw ValidationException::withMessages(['progress' => "L'avancement cumulé ({$progress} %) ne peut pas être inférieur à celui de la situation précédente ({$previousProgress} %)."]);
        }

        return $progress;
    }

    /** @param  array<int|string, float|int|string>  $works */
    private function weightedProgress(Contract $contract, array $works): float
    {
        $quoteWorks = QuoteWork::query()->where('quote_id', (int) $contract->quote_id)->get()->keyBy('id');

        $total = 0;
        $done = 0.0;

        foreach ($quoteWorks as $work) {
            $amount = $work->total();
            $total += $amount;
            $done += $amount * (float) ($works[$work->id] ?? 0);
        }

        foreach (array_keys($works) as $id) {
            if (! $quoteWorks->has((int) $id)) {
                throw ValidationException::withMessages(['works' => 'Un ouvrage n\'appartient pas au devis de ce marché.']);
            }
        }

        return $total === 0 ? 0.0 : round($done / $total, 1);
    }

    /** @param  array<int|string, float|int|string>|null  $works */
    private function saveLines(Situation $situation, ?array $works): void
    {
        if ($works === null || $works === []) {
            return;
        }

        $situation->lines()->delete();

        foreach ($works as $workId => $progress) {
            $situation->lines()->create(['quote_work_id' => (int) $workId, 'progress' => $progress]);
        }
    }
}
