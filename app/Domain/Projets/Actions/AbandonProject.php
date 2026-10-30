<?php

declare(strict_types=1);

namespace App\Domain\Projets\Actions;

use App\Domain\Projets\Models\Project;
use App\Domain\Projets\States\Abandonne;
use App\Domain\Projets\States\ProjectState;
use Illuminate\Validation\ValidationException;

/**
 * RG4.1 : un projet peut être abandonné (perdu, annulé) à tout statut
 * antérieur à « Marché ». Le motif est obligatoire.
 */
final class AbandonProject
{
    public function execute(Project $project, string $reason): Project
    {
        if (! in_array($project->status->getValue(), ProjectState::ABANDONABLE, true)) {
            throw ValidationException::withMessages([
                'status' => 'Un projet ne peut être abandonné qu\'avant le statut « Marché ».',
            ]);
        }

        $project->abandon_reason = $reason;
        $project->status->transitionTo(Abandonne::class);

        return $project;
    }
}
