<?php

declare(strict_types=1);

namespace App\Domain\Situations\Actions;

use App\Domain\Marches\Models\Contract;
use App\Domain\Situations\Models\Situation;
use App\Domain\Situations\Models\SituationPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validation d'une situation (la fige, RG11.3) et enregistrement des paiements du client.
 */
final class DecideSituation
{
    public function validate(Situation $situation, User $by): Situation
    {
        if ($situation->created_by === $by->id) {
            throw ValidationException::withMessages(['status' => 'L\'auteur de la situation ne peut pas la valider.']);
        }

        return DB::transaction(function () use ($situation, $by): Situation {
            $situation = Situation::query()->whereKey($situation->id)->lockForUpdate()->firstOrFail();

            if (! $situation->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Cette situation est déjà validée.']);
            }

            if ((float) $situation->progress_cumul < (float) $situation->previous_progress) {
                throw ValidationException::withMessages(['progress' => 'L\'avancement cumulé est inférieur à celui de la situation précédente.']);
            }

            // Le montant du marché est celui d'un éventuel avenant validé depuis la création du brouillon.
            $situation->update([
                'market_amount' => Contract::query()->with('amendments')->findOrFail($situation->contract_id)->totals()['updatedAmount'],
                'status' => Situation::VALIDATED,
                'validated_by' => $by->id,
                'validated_at' => now(),
            ]);

            return $situation;
        });
    }

    public function recordPayment(Situation $situation, int $amount, string $paidAt, ?string $reference, User $by): SituationPayment
    {
        return DB::transaction(function () use ($situation, $amount, $paidAt, $reference, $by): SituationPayment {
            $situation = Situation::query()->whereKey($situation->id)->lockForUpdate()->firstOrFail();

            if ($situation->status !== Situation::VALIDATED) {
                throw ValidationException::withMessages(['status' => 'Seule une situation validée et non soldée reçoit un paiement.']);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à 0.']);
            }

            if ($paidAt > now()->toDateString()) {
                throw ValidationException::withMessages(['paid_at' => 'La date du paiement ne peut pas être dans le futur.']);
            }

            $remaining = $situation->computation()['netTtc'] - $situation->paidAmount();

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => "Le paiement dépasse le solde restant dû ({$remaining} FCFA)."]);
            }

            $payment = $situation->payments()->create(['paid_at' => $paidAt, 'amount' => $amount, 'reference' => $reference, 'recorded_by' => $by->id]);

            if ($amount === $remaining) {
                $situation->update(['status' => Situation::PAID]);
            }

            return $payment;
        });
    }
}
