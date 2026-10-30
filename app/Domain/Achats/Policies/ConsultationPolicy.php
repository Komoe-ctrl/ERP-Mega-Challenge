<?php

declare(strict_types=1);

namespace App\Domain\Achats\Policies;

use App\Domain\Achats\Models\Consultation;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Les consultations sont conduites par le Service Achats BTP (prise en charge
 * d'une demande validée, étapes 3 à 6 du processus) ; les autres profils Achats
 * les consultent, dans la limite de leurs chantiers (RG7.2).
 */
final class ConsultationPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Achats;
    }

    public function view(User $user, Model $model): bool
    {
        return $model instanceof Consultation
            && $this->allows($user, PermissionAction::View)
            && (bool) $model->chantier?->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::ServiceAchatsBtp->value) && $this->allows($user, PermissionAction::Create);
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof Consultation
            && $model->status !== Consultation::AWARDED
            && $user->hasRole(RoleName::ServiceAchatsBtp->value)
            && $this->allows($user, PermissionAction::Update);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
