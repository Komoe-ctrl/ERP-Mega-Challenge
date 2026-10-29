<?php

declare(strict_types=1);

namespace App\Domain\Shared\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base des policies : une policy par modèle, les droits viennent de la
 * convention `<module>.<action>` (spatie/laravel-permission).
 *
 * Une policy fille déclare seulement son module :
 *
 *     final class AgencyPolicy extends ModulePolicy
 *     {
 *         protected function module(): Module { return Module::Administration; }
 *     }
 */
abstract class ModulePolicy
{
    abstract protected function module(): Module;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, PermissionAction::View);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, PermissionAction::View);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, PermissionAction::Create);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, PermissionAction::Update);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, PermissionAction::Delete);
    }

    /**
     * Séparation des tâches : l'auteur d'une demande, d'un devis ou d'une
     * facture ne peut jamais la valider lui-même. À appeler depuis la méthode
     * `validate` des policies métier en indiquant la colonne de l'auteur.
     */
    protected function canValidateOthers(User $user, Model $record, string $authorColumn = 'created_by'): bool
    {
        return $this->allows($user, PermissionAction::Validate)
            && (int) $record->getAttribute($authorColumn) !== $user->getKey();
    }

    protected function allows(User $user, PermissionAction $action): bool
    {
        return $user->is_active && $user->can($this->module()->permission($action));
    }
}
