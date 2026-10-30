<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chantiers;

use App\Domain\Chantiers\Actions\RecordProgress;
use App\Domain\Chantiers\Data\ChantierData;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Support\PlanningCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chantiers\RecordProgressRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Saisie de l'avancement par le chef de chantier (RG7.4).
 */
final class ProgressController extends Controller
{
    public function create(Chantier $chantier): Response
    {
        $this->authorize('update', $chantier);
        $planning = PlanningCalculator::fromSettings();
        $today = now();

        return Inertia::render('Chantiers/Avancement', [
            'chantier' => ['id' => $chantier->id, 'code' => $chantier->code, 'name' => $chantier->name],
            'tasks' => $chantier->tasks()->where('is_milestone', false)->get()
                ->map(fn ($t): array => ChantierData::task($t, $planning, $today))->values(),
            'thresholds' => ['risk' => $planning->riskThresholdPoints, 'staleDays' => $planning->staleDays],
        ]);
    }

    public function store(RecordProgressRequest $request, Chantier $chantier, RecordProgress $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $count = $action->execute($chantier, $user, $request->entries());
        $this->toast("{$count} tâche(s) mise(s) à jour.");

        return to_route('chantiers.show', $chantier);
    }
}
