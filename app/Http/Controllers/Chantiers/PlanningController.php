<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chantiers;

use App\Domain\Chantiers\Actions\AddTask;
use App\Domain\Chantiers\Actions\ImportPlanning;
use App\Domain\Chantiers\Models\Chantier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chantiers\ImportPlanningRequest;
use App\Http\Requests\Chantiers\StoreTaskRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

/**
 * Planning d'un chantier (RG7.3) : saisie d'une tâche ou import Excel.
 */
final class PlanningController extends Controller
{
    public function store(StoreTaskRequest $request, Chantier $chantier, AddTask $action): RedirectResponse
    {
        $action->execute($chantier, $request->taskData());
        $this->toast('Tâche ajoutée au planning.');

        return back();
    }

    public function import(ImportPlanningRequest $request, Chantier $chantier, ImportPlanning $action): RedirectResponse
    {
        $file = $request->file('file');
        assert($file instanceof UploadedFile);
        $count = $action->execute($chantier, $file);
        $this->toast("Planning importé : {$count} tâche(s) créée(s).");

        return back();
    }
}
