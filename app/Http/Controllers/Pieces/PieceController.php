<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pieces;

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Pieces\Actions\AdvancePiece;
use App\Domain\Pieces\Actions\RejectPiece;
use App\Domain\Pieces\Actions\SavePiece;
use App\Domain\Pieces\Actions\SubmitPiece;
use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Support\BudgetPanel;
use App\Domain\Pieces\Support\ValidationCircuit;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Support\Present;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pieces\SavePieceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Saisie commune des pièces (note de frais, recette, pièce de caisse) : liste, saisie, soumission,
 * validation par étape, rejet. Les droits viennent de la policy ; le circuit, des étapes paramétrées.
 */
final class PieceController extends Controller
{
    public function index(Request $request, PieceKind $kind, ValidationCircuit $circuit): Response
    {
        $user = $this->user($request);
        $this->authorize('viewAny', [Piece::class, $kind]);

        $pieces = Piece::query()
            ->with(['requester', 'lines.rubrique'])
            ->where('kind', $kind->value)
            ->when($request->string('statut')->toString() !== '', fn ($q) => $q->where('status', $request->string('statut')->toString()))
            ->latest('id')
            ->limit(200)
            ->get();

        $steps = $circuit->activeSteps();

        return Inertia::render('Pieces/Index', [
            'kind' => ['value' => $kind->value, 'label' => $kind->label()],
            'pieces' => $pieces->map(fn (Piece $p): array => $this->row($p, $user, $circuit))->values(),
            // Ligne d'état du circuit : combien de pièces attendent chaque étape.
            'waiting' => $steps->map(fn ($s): array => [
                'step' => $s->label,
                'count' => $pieces->where('status', PieceStatus::EnValidation)->where('current_step', $s->position)->count(),
            ])->values(),
            'steps' => $steps->map(fn ($s): array => ['position' => $s->position, 'label' => $s->label, 'button' => $s->button_label])->values(),
            'rubriques' => Rubrique::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'label']),
            'projects' => $this->projects(),
            'can' => [
                'create' => $user->can('create', [Piece::class, $kind]),
            ],
        ]);
    }

    public function show(Request $request, PieceKind $kind, Piece $piece, ValidationCircuit $circuit): Response
    {
        $this->authorize('view', $piece);
        abort_unless($piece->kind === $kind, 404);

        $piece->load(['requester', 'lines.rubrique', 'events.user', 'project', 'lot', 'component']);

        return Inertia::render('Pieces/Show', [
            'piece' => [
                ...$this->row($piece, $this->user($request), $circuit),
                'project_id' => $piece->project_id,
                'project_lot_id' => $piece->project_lot_id,
                'lot_component_id' => $piece->lot_component_id,
                'lines' => $piece->lines->map(fn ($l): array => [
                    'designation' => $l->designation,
                    'quantity' => $l->quantity,
                    'unit_price' => $l->unit_price,
                    'amount' => $l->amount,
                    'rubrique_id' => $l->rubrique_id,
                    'rubrique' => Present::text($l, 'rubrique.code').' – '.Present::text($l, 'rubrique.label'),
                ])->values(),
                'events' => $piece->events->map(fn ($e): array => [
                    'at' => $e->created_at->format('Y-m-d H:i'),
                    'by' => $e->user instanceof User ? $e->user->name : 'Système',
                    'action' => $e->action,
                    'step' => $e->step_label,
                    'comment' => $e->comment,
                ])->values(),
            ],
        ]);
    }

    public function store(SavePieceRequest $request, PieceKind $kind, SavePiece $action): RedirectResponse
    {
        $piece = $action->execute($kind, $request->toData(), $this->user($request));

        if ($request->hasFile('proof')) {
            $piece->addMediaFromRequest('proof')->toMediaCollection('justificatifs');
        }

        $this->toast($kind->label().' enregistrée.');

        return to_route('pieces.show', [$kind->value, $piece]);
    }

    public function update(SavePieceRequest $request, PieceKind $kind, Piece $piece, SavePiece $action): RedirectResponse
    {
        abort_unless($piece->kind === $kind, 404);
        $action->execute($kind, $request->toData(), $this->user($request), $piece);

        if ($request->hasFile('proof')) {
            $piece->addMediaFromRequest('proof')->toMediaCollection('justificatifs');
        }

        $this->toast($kind->label().' mise à jour.');

        return to_route('pieces.show', [$kind->value, $piece]);
    }

    public function destroy(Request $request, PieceKind $kind, Piece $piece): RedirectResponse
    {
        abort_unless($piece->kind === $kind, 404);
        $this->authorize('delete', $piece);
        $piece->delete();

        $this->toast($kind->label().' supprimée.');

        return to_route('pieces.index', $kind->value);
    }

    public function submit(Request $request, PieceKind $kind, Piece $piece, SubmitPiece $action): RedirectResponse
    {
        abort_unless($piece->kind === $kind, 404);
        $this->authorize('submit', $piece);
        $action->execute($piece, $this->user($request));

        $this->toast($kind->label().' soumise à validation.');

        return back();
    }

    public function advance(Request $request, PieceKind $kind, Piece $piece, AdvancePiece $action): RedirectResponse
    {
        abort_unless($piece->kind === $kind, 404);
        $this->authorize('decide', $piece);
        $action->execute($piece, $this->user($request), $request->filled('comment') ? $request->string('comment')->toString() : null);

        $this->toast('Étape validée.');

        return back();
    }

    public function reject(Request $request, PieceKind $kind, Piece $piece, RejectPiece $action): RedirectResponse
    {
        abort_unless($piece->kind === $kind, 404);
        $this->authorize('decide', $piece);
        $validated = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $action->execute($piece, $this->user($request), $validated['comment']);

        $this->toast($kind->label().' rejetée.');

        return back();
    }

    /**
     * Panneau budget d'une rubrique (montant, consommé + en cours, restant, taux), pour l'exercice de la période.
     */
    public function budget(Request $request, PieceKind $kind, BudgetPanel $panel): JsonResponse
    {
        $this->authorize('viewAny', [Piece::class, $kind]);

        $validated = $request->validate([
            'rubrique_id' => ['required', 'integer', 'exists:rubriques,id'],
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'piece' => ['nullable', 'integer'],
        ]);

        $day = $validated['period'].'-01';
        $year = FiscalYear::query()->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->first();
        abort_if($year === null, 422, 'Aucun exercice ne couvre cette période.');

        $rubrique = Rubrique::query()->findOrFail((int) $validated['rubrique_id']);

        return response()->json($panel->for($rubrique, $year->id, isset($validated['piece']) ? (int) $validated['piece'] : null));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Piece $p, User $user, ValidationCircuit $circuit): array
    {
        $step = $circuit->currentStep($p);

        return [
            'id' => $p->id,
            'number' => $p->number,
            'title' => $p->title,
            'period' => $p->period,
            'requester' => Present::text($p, 'requester.name', 'Système'),
            'bc_number' => $p->bc_number,
            'observation' => $p->observation,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'total' => $p->total,
            'waiting' => $step?->label,
            'proof' => $p->getFirstMedia('justificatifs')?->file_name,
            'can' => [
                'update' => $user->can('update', $p),
                'decide' => $user->can('decide', $p),
                'button' => $step?->button_label,
            ],
        ];
    }

    /**
     * Arbre d'imputation analytique : projet → produit / lot / section → composante.
     *
     * @return list<array{id: int, name: string, lots: list<array{id: int, name: string, components: list<array{id: int, name: string}>}>}>
     */
    private function projects(): array
    {
        $tree = [];

        foreach (Project::query()->with('lots.components')->orderBy('name')->get() as $project) {
            $lots = [];

            foreach ($project->lots as $lot) {
                $components = [];

                foreach ($lot->components as $component) {
                    $components[] = ['id' => $component->id, 'name' => $component->name];
                }

                $lots[] = ['id' => $lot->id, 'name' => $lot->name, 'components' => $components];
            }

            $tree[] = ['id' => $project->id, 'name' => $project->name, 'lots' => $lots];
        }

        return $tree;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
