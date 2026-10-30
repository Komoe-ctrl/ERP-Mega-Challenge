<?php

declare(strict_types=1);

namespace App\Domain\Budget\Actions;

use App\Domain\Budget\Models\BudgetExpense;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Support\BudgetLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * RG10.4 : les dépenses diverses du chantier (petits achats, transport) sont
 * saisies avec justificatif et imputées à un poste : elles s'ajoutent au coût
 * réel du poste, sous verrou de budget.
 */
final class RecordExpense
{
    /**
     * @param  array{budget_line_id: int, label: string, amount: int}  $data
     */
    public function execute(Chantier $chantier, array $data, UploadedFile $receipt, User $by): BudgetExpense
    {
        return BudgetLock::run(
            BudgetLine::query()->where('chantier_id', $chantier->id),
            function (Collection $lines) use ($chantier, $data, $receipt, $by): BudgetExpense {
                /** @var BudgetLine|null $line */
                $line = $lines->firstWhere('id', $data['budget_line_id']);

                if ($line === null) {
                    throw ValidationException::withMessages(['budget_line_id' => 'Ce poste n\'appartient pas au chantier.']);
                }

                $line->update(['actual_amount' => $line->actual_amount + $data['amount']]);

                $expense = BudgetExpense::query()->create([
                    'chantier_id' => $chantier->id,
                    'budget_line_id' => $line->id,
                    'label' => $data['label'],
                    'amount' => $data['amount'],
                    'spent_on' => now()->toDateString(),
                    'user_id' => $by->id,
                ]);
                $expense->addMedia($receipt)->toMediaCollection('receipts');

                return $expense;
            },
        );
    }
}
