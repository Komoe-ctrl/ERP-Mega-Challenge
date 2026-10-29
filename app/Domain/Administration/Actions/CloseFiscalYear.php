<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\FiscalYear;
use App\Models\User;
use DomainException;

/**
 * Clôture un exercice comptable : il passe en lecture seule (RG1.4).
 */
final class CloseFiscalYear
{
    public function execute(FiscalYear $fiscalYear, User $by): FiscalYear
    {
        if ($fiscalYear->isClosed()) {
            throw new DomainException('Cet exercice est déjà clôturé.');
        }

        $fiscalYear->update([
            'status' => FiscalYear::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $by->id,
        ]);

        return $fiscalYear;
    }
}
