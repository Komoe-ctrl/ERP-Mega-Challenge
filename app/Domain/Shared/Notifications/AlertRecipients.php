<?php

declare(strict_types=1);

namespace App\Domain\Shared\Notifications;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Destinataires par défaut des alertes (section « Notifications et alertes »
 * du cahier des charges).
 */
final class AlertRecipients
{
    /**
     * Utilisateurs actifs d'un ou plusieurs profils.
     *
     * @return Collection<int, User>
     */
    public static function withRoles(RoleName ...$roles): Collection
    {
        return User::query()->where('is_active', true)->role(array_map(fn (RoleName $r): string => $r->value, $roles))->get();
    }

    /**
     * Membres de l'équipe d'un chantier ayant l'un des rôles d'affectation donnés.
     *
     * @return Collection<int, User>
     */
    public static function onChantier(Chantier $chantier, string ...$roles): Collection
    {
        return $chantier->team()->where('users.is_active', true)->wherePivotIn('role', $roles)->get();
    }
}
