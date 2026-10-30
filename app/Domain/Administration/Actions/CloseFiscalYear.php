<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\FiscalYear;
use App\Models\User;
use DomainException;

/**
 * Clôture un exercice comptable : il passe en lecture seule (RG1.4).
 * Le motif de clôture est inscrit au journal des activités.
 */
final class CloseFiscalYear
{
    public function execute(FiscalYear $fiscalYear, User $by, ?string $reason = null): FiscalYear
    {
        if ($fiscalYear->isClosed()) {
            throw new DomainException('Cet exercice est déjà clôturé.');
        }

        $fiscalYear->update([
            'status' => FiscalYear::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $by->id,
        ]);

        if ($reason !== null) {
            activity('administration')
                ->performedOn($fiscalYear)
                ->causedBy($by)
                ->event('validation')
                ->withProperties(['motif' => $reason])
                ->log('Clôture de l\'exercice');
        }

        return $fiscalYear;
    }
}
