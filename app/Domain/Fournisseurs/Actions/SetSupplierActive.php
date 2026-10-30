<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Actions;

use App\Domain\Fournisseurs\Models\Supplier;
use App\Models\User;

/**
 * Désactive ou réactive un fournisseur : jamais supprimé, son historique de
 * commandes et de factures est conservé. Le motif est inscrit au journal.
 */
final class SetSupplierActive
{
    public function execute(Supplier $supplier, bool $active, User $by, ?string $reason = null): void
    {
        $supplier->update(['is_active' => $active]);

        if (! $active && $reason !== null) {
            activity('fournisseurs')
                ->performedOn($supplier)
                ->causedBy($by)
                ->event('deactivation')
                ->withProperties(['motif' => $reason])
                ->log('Désactivation du fournisseur');
        }
    }
}
