<?php

declare(strict_types=1);

namespace App\Domain\Projets\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Projets\Models\Project;
use App\Domain\Projets\States\ProjectState;
use Illuminate\Validation\ValidationException;

/**
 * Passe un projet au statut suivant de son parcours.
 *
 * RG4.2 : le passage à « En cours » exige au moins un chantier créé, avec un
 * budget (déboursé sec) et un responsable.
 */
final class AdvanceProject
{
    public function execute(Project $project): Project
    {
        $next = $project->status->next();

        if ($next === null) {
            throw ValidationException::withMessages(['status' => 'Ce projet est au dernier statut de son parcours.']);
        }

        if ($next === 'en_cours' && ! $this->hasReadyChantier($project)) {
            throw ValidationException::withMessages([
                'status' => 'Impossible de passer à « En cours » : créez au moins un chantier avec un budget (déboursé sec) et un responsable.',
            ]);
        }

        $target = ProjectState::resolveStateClass($next);
        assert(is_string($target));
        $project->status->transitionTo($target);

        return $project;
    }

    private function hasReadyChantier(Project $project): bool
    {
        return $project->chantiers()->get()->contains(fn (Chantier $c): bool => $c->isReadyToStart());
    }
}
