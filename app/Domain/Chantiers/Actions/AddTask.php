<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;

/**
 * Ajoute une tâche ou un jalon au planning (RG7.3). Un jalon n'a pas de durée.
 */
final class AddTask
{
    /**
     * @param  array{name: string, start_date: string, end_date: string, weight: int, is_milestone: bool}  $data
     */
    public function execute(Chantier $chantier, array $data): Task
    {
        return $chantier->tasks()->create([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['is_milestone'] ? $data['start_date'] : $data['end_date'],
            'weight' => $data['weight'],
            'is_milestone' => $data['is_milestone'],
            'progress' => 0,
            'last_update_on' => now()->toDateString(),
        ]);
    }
}
