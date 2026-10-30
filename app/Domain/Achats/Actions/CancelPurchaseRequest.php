<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\States\Annulee;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * RG8.4 : l'auteur peut annuler sa demande tant qu'elle n'est pas validée.
 */
final class CancelPurchaseRequest
{
    public function execute(PurchaseRequest $request, User $by, string $reason): PurchaseRequest
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif de l\'annulation.']);
        }

        $request->status->transitionTo(Annulee::class);
        $request->events()->create(['user_id' => $by->id, 'action' => 'Annulée par son auteur', 'reason' => trim($reason)]);

        return $request;
    }
}
