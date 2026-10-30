<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\Attendance;
use App\Models\User;

/**
 * Pointage d'arrivée de l'utilisateur authentifié : un seul pointage par jour,
 * la première heure est conservée si l'utilisateur pointe deux fois.
 */
final class ClockIn
{
    public function execute(User $user): Attendance
    {
        return Attendance::query()->firstOrCreate(
            ['user_id' => $user->id, 'work_date' => now()->toDateString()],
            ['arrived_at' => now()],
        );
    }

    /** Pointage du jour, s'il existe. */
    public function today(User $user): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();
    }
}
