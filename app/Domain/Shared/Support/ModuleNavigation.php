<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Entrées du menu latéral : un module n'apparaît que s'il est activé, s'il a
 * une page d'entrée et si l'utilisateur a le droit de le consulter (CA1).
 * Ce n'est qu'un confort d'affichage : le serveur vérifie les droits à chaque
 * requête (middleware `can:` et policies).
 */
final class ModuleNavigation
{
    /**
     * @return list<array{key: string, title: string, icon: string, href: string}>
     */
    public static function for(?User $user): array
    {
        if ($user === null || ! $user->is_active) {
            return [];
        }

        $items = [];
        foreach (Module::cases() as $module) {
            $route = $module->entryRoute();

            if (! Route::has($route)
                || ! $module->isEnabled()
                || ! $user->can($module->permission(PermissionAction::View))) {
                continue;
            }

            $items[] = [
                'key' => $module->value,
                'title' => $module->label(),
                'icon' => $module->icon(),
                'href' => route($route, absolute: false),
            ];
        }

        return $items;
    }
}
