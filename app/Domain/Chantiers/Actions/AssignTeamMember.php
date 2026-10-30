<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Affecte explicitement un utilisateur à un chantier avec un rôle (RG1.1, RG7.2).
 */
final class AssignTeamMember
{
    public function execute(Chantier $chantier, User $user, string $role): void
    {
        if (! in_array($role, Chantier::TEAM_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Rôle d\'affectation inconnu.']);
        }

        if ($chantier->team()->where('users.id', $user->id)->wherePivot('role', $role)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Cet utilisateur a déjà ce rôle sur le chantier.']);
        }

        $chantier->team()->attach($user->id, ['role' => $role]);
    }
}
