<?php

declare(strict_types=1);

namespace App\Domain\Projets\Actions;

use App\Domain\Projets\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un projet : il démarre au statut « Étude » avec un numéro unique
 * PRJ-AAAA-NNN, attribué dans une transaction pour éviter les doublons.
 */
final class CreateProject
{
    /**
     * @param  array{name: string, client_id: int, location: string, manager_id: int, agency_id: int, budget: int, start_date: string, end_date: string}  $data
     */
    public function execute(array $data, User $by): Project
    {
        return DB::transaction(function () use ($data, $by): Project {
            $year = now()->year;
            $last = Project::withTrashed()
                ->where('number', 'like', "PRJ-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');
            $sequence = is_string($last) ? ((int) substr($last, -3)) + 1 : 1;

            $project = new Project($data + [
                'number' => sprintf('PRJ-%d-%03d', $year, $sequence),
                'created_by' => $by->id,
            ]);
            $project->save();

            return $project;
        });
    }
}
