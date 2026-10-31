<?php

declare(strict_types=1);

namespace App\Domain\Marches\Actions;

use App\Domain\Devis\Models\Quote;
use App\Domain\Marches\Models\Contract;
use App\Domain\Projets\States\ProjectState;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création d'un marché (M6).
 *
 * RG6.1 : un marché naît d'un devis validé ; son montant initial reprend le total HT du devis. Un devis
 * ne donne qu'un seul marché. Le projet passe au statut « Marché ».
 */
final class CreateContract
{
    /**
     * @param  array{signed_at: string, deadline_days: int, advance_rate: float|int|string, retention_rate: float|int|string, payment_days: int}  $data
     */
    public function execute(Quote $quote, array $data, User $by): Contract
    {
        if ($quote->status->getValue() !== 'valide') {
            throw ValidationException::withMessages(['quote_id' => 'Seul un devis validé peut donner un marché (RG6.1).']);
        }

        if (Contract::query()->where('quote_id', $quote->id)->exists()) {
            throw ValidationException::withMessages(['quote_id' => 'Ce devis a déjà donné un marché.']);
        }

        if ($data['signed_at'] > now()->toDateString()) {
            throw ValidationException::withMessages(['signed_at' => 'La signature ne peut pas être dans le futur.']);
        }

        return DB::transaction(function () use ($quote, $data, $by): Contract {
            $contract = Contract::query()->create($data + [
                'number' => DocumentNumbers::next('marche', 'MAR-{YYYY}-{SEQ:5}'),
                'quote_id' => $quote->id,
                'client_id' => $quote->client_id,
                'project_id' => $quote->project_id,
                'initial_amount' => $quote->totals()['ht'],
                'created_by' => $by->id,
            ]);

            $this->moveProjectToContract($contract);

            return $contract;
        });
    }

    /** Fait avancer le projet, étape par étape, jusqu'au statut « Marché ». */
    private function moveProjectToContract(Contract $contract): void
    {
        $project = $contract->project;

        while ($project !== null && in_array($project->status->getValue(), ['etude', 'devis', 'appel_offres'], true)) {
            $next = $project->status->next();
            $target = $next !== null ? ProjectState::resolveStateClass($next) : null;
            assert(is_string($target));
            $project->status->transitionTo($target);
        }
    }
}
