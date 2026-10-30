<?php

declare(strict_types=1);

namespace App\Domain\Achats\Policies;

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bons de commande : émis par le Service Achats BTP ; consultables par les
 * profils Achats dans la limite de leurs chantiers (RG7.2). La réception des
 * livraisons relève du Service Achats BTP et du magasinier.
 */
final class PurchaseOrderPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Achats;
    }

    public function view(User $user, Model $model): bool
    {
        return $model instanceof PurchaseOrder
            && $this->allows($user, PermissionAction::View)
            && (bool) $model->chantier?->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::ServiceAchatsBtp->value) && $this->allows($user, PermissionAction::Create);
    }

    /** Enregistrer une réception : Service Achats BTP ou magasinier. */
    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->hasAnyRole([RoleName::ServiceAchatsBtp->value, RoleName::Magasinier->value])
            && $this->allows($user, PermissionAction::Update)
            && in_array($order->status, [PurchaseOrder::ISSUED, PurchaseOrder::PARTIAL], true);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
