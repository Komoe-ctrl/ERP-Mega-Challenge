<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Data;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Chantiers\Support\PlanningCalculator;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Chantiers et tâches tels que les affichent les écrans (types TypeScript
 * Chantier et Task). L'état des tâches est calculé ici, jamais par l'écran.
 */
final class ChantierData
{
    private const ROLE_LABELS = [
        Chantier::ROLE_CONDUCTEUR => 'Conducteur de travaux',
        Chantier::ROLE_CHEF => 'Chef de chantier',
        Chantier::ROLE_MAGASINIER => 'Magasinier de chantier',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function from(Chantier $chantier): array
    {
        $team = $chantier->team;
        $names = fn (string $role): string => $team
            ->filter(fn (User $u): bool => self::pivotRole($u) === $role)
            ->map(fn (User $u): string => $u->name)->implode(', ');

        return [
            'id' => $chantier->id,
            'code' => $chantier->code,
            'name' => $chantier->name,
            'projectId' => $chantier->project_id,
            'projectName' => data_get($chantier, 'project.name', ''),
            'location' => $chantier->location,
            'startDate' => $chantier->start_date->toDateString(),
            'endDate' => $chantier->end_date->toDateString(),
            'status' => $chantier->status,
            'manager' => $names(Chantier::ROLE_CONDUCTEUR),
            'foreman' => $names(Chantier::ROLE_CHEF),
            'team' => $team->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => self::ROLE_LABELS[self::pivotRole($u)] ?? self::pivotRole($u),
            ])->values()->all(),
            'lastPhoto' => [],
        ];
    }

    /** Rôle d'affectation porté par la table pivot (conducteur, chef_chantier, magasinier). */
    public static function pivotRole(User $user): string
    {
        $role = data_get($user, 'pivot.role');

        return is_string($role) ? $role : '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function task(Task $task, PlanningCalculator $planning, CarbonInterface $today): array
    {
        return [
            'id' => $task->id,
            'chantierId' => $task->chantier_id,
            'name' => $task->name,
            'start' => $task->start_date->toDateString(),
            'end' => $task->end_date->toDateString(),
            'progress' => $task->progress,
            'weight' => $task->weight,
            'milestone' => $task->is_milestone,
            'lastUpdate' => $task->last_update_on->toDateString(),
            'comment' => $task->comment,
            'theoretical' => $planning->theoretical($task, $today),
            'health' => $planning->health($task, $today),
        ];
    }
}
