<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Models\User;

/**
 * Désactive ou réactive un compte. Un compte n'est jamais supprimé.
 */
final class SetUserActive
{
    public function execute(User $user, bool $active): User
    {
        $user->is_active = $active;
        $user->save();

        return $user;
    }
}
