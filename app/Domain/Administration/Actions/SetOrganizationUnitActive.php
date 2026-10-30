<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Site;

/**
 * Désactive ou réactive une agence, un service ou un site. Jamais de
 * suppression : l'historique est conservé. Le motif de désactivation est
 * inscrit au journal des activités (RG1.5).
 */
final class SetOrganizationUnitActive
{
    public function execute(Agency|Service|Site $unit, bool $active, ?string $reason = null): void
    {
        $unit->update(['is_active' => $active]);

        if (! $active && $reason !== null) {
            activity('administration')
                ->performedOn($unit)
                ->event('deactivation')
                ->withProperties(['motif' => $reason])
                ->log('Désactivation');
        }
    }
}
