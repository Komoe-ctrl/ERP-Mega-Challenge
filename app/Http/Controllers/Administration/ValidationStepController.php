<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Models\Role;
use App\Domain\Pieces\Actions\SaveValidationSteps;
use App\Domain\Pieces\Models\ValidationStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Étapes de validation (Administration) : le circuit appliqué à toutes les pièces.
 */
final class ValidationStepController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ValidationStep::class);

        return Inertia::render('Administration/EtapesValidation', [
            'steps' => ValidationStep::query()->orderBy('position')->get()->map(fn (ValidationStep $s): array => [
                'label' => $s->label,
                'role_name' => $s->role_name,
                'button_label' => $s->button_label,
                'is_active' => $s->is_active,
            ])->values(),
            'roles' => Role::query()->orderBy('name')->get()->map(fn (Role $r): array => ['value' => $r->name, 'label' => $r->label()])->values(),
            'can' => ['update' => request()->user()?->can('update', new ValidationStep) ?? false],
        ]);
    }

    public function update(Request $request, SaveValidationSteps $action): RedirectResponse
    {
        $this->authorize('update', new ValidationStep);

        $validated = $request->validate([
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.label' => ['required', 'string', 'max:150'],
            'steps.*.role_name' => ['required', 'string', 'max:100'],
            'steps.*.button_label' => ['required', 'string', 'max:100'],
            'steps.*.is_active' => ['required', 'boolean'],
        ]);

        /** @var list<array{label: string, role_name: string, button_label: string, is_active: bool}> $steps */
        $steps = array_values($validated['steps']);
        $action->execute($steps);

        $this->toast('Circuit de validation enregistré.');

        return back();
    }
}
