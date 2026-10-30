<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Droits sur les fournisseurs (matrice de la section 4). Un fournisseur n'est
 * jamais supprimé : il est désactivé.
 */
final class SupplierPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Fournisseurs;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
