<?php

declare(strict_types=1);

namespace App\Domain\Marches\Actions;

use App\Domain\Marches\Models\Contract;
use App\Domain\Marches\Models\ContractAmendment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avenants d'un marché (M6, RG6.2). Un avenant saisi est en attente ; il ne modifie le montant et
 * le délai qu'une fois validé, par une autre personne que son auteur.
 */
final class ManageAmendment
{
    /**
     * @param  array{label: string, amount: int, days: int, amendment_date: string}  $data
     */
    public function add(Contract $contract, array $data, User $by): ContractAmendment
    {
        if ($data['amount'] === 0 && $data['days'] === 0) {
            throw ValidationException::withMessages(['amount' => 'Un avenant modifie le montant ou le délai : les deux ne peuvent pas être nuls.']);
        }

        return DB::transaction(function () use ($contract, $data, $by): ContractAmendment {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $count = $contract->amendments()->count();

            return $contract->amendments()->create($data + [
                'number' => sprintf('AV-%02d', $count + 1),
                'created_by' => $by->id,
            ]);
        });
    }

    public function validate(Contract $contract, ContractAmendment $amendment, User $by): ContractAmendment
    {
        if ($amendment->contract_id !== $contract->id) {
            abort(404);
        }

        if ($amendment->is_validated) {
            throw ValidationException::withMessages(['amendment' => 'Cet avenant est déjà validé.']);
        }

        if ($amendment->created_by === $by->id) {
            throw ValidationException::withMessages(['amendment' => 'L\'auteur de l\'avenant ne peut pas le valider.']);
        }

        return DB::transaction(function () use ($contract, $amendment, $by): ContractAmendment {
            $contract->load('amendments');

            if ($contract->totals()['updatedAmount'] + $amendment->amount < 0) {
                throw ValidationException::withMessages(['amendment' => 'Le montant actualisé du marché ne peut pas devenir négatif.']);
            }

            $amendment->update(['is_validated' => true, 'validated_by' => $by->id, 'validated_at' => now()]);

            return $amendment;
        });
    }
}
