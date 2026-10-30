<?php

declare(strict_types=1);

namespace App\Domain\Projets\Actions;

use App\Domain\Projets\Models\Project;

/**
 * Modification des informations d'un projet. Le statut ne change jamais ici :
 * il passe par AdvanceProject ou AbandonProject. La lecture seule (RG4.3) est
 * garantie par la policy.
 */
final class UpdateProject
{
    /**
     * @param  array{name: string, client_id: int, location: string, manager_id: int, agency_id: int, budget: int, start_date: string, end_date: string}  $data
     */
    public function execute(Project $project, array $data): Project
    {
        $project->update($data);

        return $project;
    }
}
