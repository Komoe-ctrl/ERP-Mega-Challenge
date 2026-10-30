<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Shared\Enums\RoleName;
use App\Models\User;

/**
 * Page d'arrivée d'un utilisateur : celle du premier de ses rôles dont il peut
 * ouvrir l'écran, sinon le tableau de bord. Jamais une page de paramètres.
 */
final class RoleHome
{
    public static function for(?User $user): string
    {
        if ($user === null) {
            return '/dashboard';
        }

        foreach ($user->getRoleNames() as $name) {
            $role = is_string($name) ? RoleName::tryFrom($name) : null;

            if ($role === null) {
                continue;
            }

            [$path, $permission] = $role->home();

            if ($permission === null || $user->can($permission)) {
                return $path;
            }
        }

        return '/dashboard';
    }

    /** Le compte voit-il au moins un module métier (hors Administration) ? */
    public static function hasBusinessAccess(User $user): bool
    {
        return $user->getAllPermissions()->contains(
            fn ($permission): bool => str_ends_with($permission->name, '.view')
                && ! str_starts_with($permission->name, 'administration.'),
        );
    }
}
