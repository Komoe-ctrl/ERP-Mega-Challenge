<?php

declare(strict_types=1);

namespace App\Domain\Budget\Actions;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Models\BudgetRevision;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Support\BudgetLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * RG10.2 : toute révision du budget est historisée avec son motif, son auteur
 * et sa validation. Demandée, elle reste sans effet ; validée par la Direction,
 * elle modifie le budget révisé du poste sous verrou de budget.
 */
final class ManageBudgetRevision
{
    /**
     * @param  array{budget_line_id: int, amount: int, reason: string}  $data
     */
    public function request(Chantier $chantier, array $data, User $by): BudgetRevision
    {
        $line = BudgetLine::query()->whereKey($data['budget_line_id'])->where('chantier_id', $chantier->id)->first();

        if ($line === null) {
            throw ValidationException::withMessages(['budget_line_id' => 'Ce poste n\'appartient pas au chantier.']);
        }

        if ($data['amount'] === 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant de la révision ne peut pas être nul.']);
        }

        if (mb_strlen(trim($data['reason'])) < 10) {
            throw ValidationException::withMessages(['reason' => 'Le motif est obligatoire (10 caractères minimum).']);
        }

        return BudgetRevision::query()->create([
            'chantier_id' => $chantier->id,
            'budget_line_id' => $line->id,
            'amount' => $data['amount'],
            'reason' => trim($data['reason']),
            'requested_by' => $by->id,
            'status' => BudgetRevision::PENDING,
        ]);
    }

    public function validate(BudgetRevision $revision, User $by): BudgetRevision
    {
        return BudgetLock::run(
            BudgetLine::query()->where('chantier_id', $revision->chantier_id),
            function (Collection $lines) use ($revision, $by): BudgetRevision {
                $revision->refresh();

                if ($revision->status !== BudgetRevision::PENDING) {
                    throw ValidationException::withMessages(['status' => 'Cette révision a déjà reçu une décision.']);
                }

                /** @var BudgetLine|null $line */
                $line = $lines->firstWhere('id', $revision->budget_line_id);
                $revised = ($line instanceof BudgetLine ? $line->revised_amount : 0) + $revision->amount;

                if ($line === null || $revised < 0) {
                    throw ValidationException::withMessages(['amount' => 'La révision rendrait le budget du poste négatif.']);
                }

                $line->update(['revised_amount' => $revised]);
                $revision->update(['status' => BudgetRevision::VALIDATED, 'decided_by' => $by->id, 'decided_at' => now()]);

                return $revision;
            },
        );
    }

    public function reject(BudgetRevision $revision, User $by, string $reason): BudgetRevision
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Un rejet exige un motif.']);
        }

        $revision->update([
            'status' => BudgetRevision::REJECTED,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_reason' => trim($reason),
        ]);

        return $revision;
    }
}
