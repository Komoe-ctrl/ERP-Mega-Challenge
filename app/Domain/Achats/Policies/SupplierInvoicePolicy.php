<?php

declare(strict_types=1);

namespace App\Domain\Achats\Policies;

use App\Domain\Achats\Models\SupplierInvoice;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Factures fournisseurs : saisies et contrôlées par la Comptabilité et la
 * Finance (droit d'écriture sur le module Finance), payées par la Finance
 * seule (droit de validation). L'auteur d'une facture ne la contrôle jamais
 * lui-même (séparation des tâches) ; une facture n'est payable qu'une fois
 * contrôlée (RG8.10).
 */
final class SupplierInvoicePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Achats;
    }

    /** Les factures sont consultables avec le droit de lecture sur les Achats. */
    public function viewAny(User $user): bool
    {
        return $this->allows($user, PermissionAction::View);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can(Module::Finance->permission(PermissionAction::Update));
    }

    public function control(User $user, SupplierInvoice $invoice): bool
    {
        return $this->create($user)
            && $invoice->status !== SupplierInvoice::PAID
            && $invoice->created_by !== $user->id;
    }

    public function pay(User $user, SupplierInvoice $invoice): bool
    {
        return $user->is_active
            && $user->can(Module::Finance->permission(PermissionAction::Validate))
            && $invoice->status === SupplierInvoice::CONTROLLED;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
