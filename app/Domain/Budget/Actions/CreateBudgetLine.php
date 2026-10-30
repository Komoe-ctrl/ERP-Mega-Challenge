<?php

declare(strict_types=1);

namespace App\Domain\Budget\Actions;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Support\BudgetLock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Détaille le budget initial (déboursé sec) d'un chantier par poste et par
 * nature (M10). Le budget révisé démarre égal au budget initial ; il ne bouge
 * ensuite que par révisions validées (RG10.2).
 */
final class CreateBudgetLine
{
    /**
     * @param  array{post: string, nature: string, amount: int}  $data
     */
    public function execute(Chantier $chantier, array $data): BudgetLine
    {
        return BudgetLock::run(
            BudgetLine::query()->where('chantier_id', $chantier->id),
            function (Collection $lines) use ($chantier, $data): BudgetLine {
                if ($lines->contains(fn (BudgetLine $l): bool => mb_strtolower($l->post) === mb_strtolower($data['post']) && $l->nature === $data['nature'])) {
                    throw ValidationException::withMessages(['post' => 'Ce poste existe déjà pour cette nature.']);
                }

                return BudgetLine::query()->create([
                    'chantier_id' => $chantier->id,
                    'post' => $data['post'],
                    'nature' => $data['nature'],
                    'initial_amount' => $data['amount'],
                    'revised_amount' => $data['amount'],
                ]);
            },
        );
    }
}
