<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Projets\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et modification d'un chantier, avec ses deux affectations de base :
 * le conducteur de travaux (responsable) et le chef de chantier (RG1.1, RG7.1, RG7.2).
 */
final class SaveChantier
{
    /**
     * @param  array{code: string, name: string, project_id: int, location: string, start_date: string, end_date: string, status: string, conducteur_id: int, chef_id: int}  $data
     */
    public function create(array $data): Chantier
    {
        $project = Project::query()->findOrFail($data['project_id']);

        // RG4.3 : on ne crée plus de chantier sur un projet clôturé ou abandonné.
        if ($project->isReadOnly()) {
            throw ValidationException::withMessages([
                'project_id' => 'Ce projet est clôturé ou abandonné : il est en lecture seule.',
            ]);
        }

        return DB::transaction(function () use ($data): Chantier {
            $chantier = Chantier::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'project_id' => $data['project_id'],
                'location' => $data['location'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => $data['status'],
            ]);
            $this->assignLeads($chantier, $data['conducteur_id'], $data['chef_id']);

            return $chantier;
        });
    }

    /**
     * Le projet d'un chantier ne change pas après sa création.
     *
     * @param  array{code: string, name: string, project_id: int, location: string, start_date: string, end_date: string, status: string, conducteur_id: int, chef_id: int}  $data
     */
    public function update(Chantier $chantier, array $data): Chantier
    {
        return DB::transaction(function () use ($chantier, $data): Chantier {
            $chantier->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'location' => $data['location'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => $data['status'],
            ]);
            $chantier->team()->wherePivotIn('role', [Chantier::ROLE_CONDUCTEUR, Chantier::ROLE_CHEF])->detach();
            $this->assignLeads($chantier, $data['conducteur_id'], $data['chef_id']);

            return $chantier;
        });
    }

    private function assignLeads(Chantier $chantier, int $conducteurId, int $chefId): void
    {
        $chantier->team()->attach($conducteurId, ['role' => Chantier::ROLE_CONDUCTEUR]);
        $chantier->team()->attach($chefId, ['role' => Chantier::ROLE_CHEF]);
    }
}
