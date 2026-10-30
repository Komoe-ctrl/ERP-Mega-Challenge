<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projets;

use App\Domain\Administration\Models\Agency;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Clients\Models\Client;
use App\Domain\Projets\Actions\AbandonProject;
use App\Domain\Projets\Actions\AdvanceProject;
use App\Domain\Projets\Actions\CreateProject;
use App\Domain\Projets\Actions\UpdateProject;
use App\Domain\Projets\Data\ProjectData;
use App\Domain\Projets\Models\Project;
use App\Domain\Projets\States\ProjectState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projets\SaveProjectRequest;
use App\Http\Requests\Projets\UploadProjectDocumentRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Projets (M4) : liste filtrable (CA4), fiche, statuts et documents.
 */
final class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
            'client' => $request->string('client')->toString(),
            'manager' => $request->string('manager')->toString(),
            'agency' => $request->string('agency')->toString(),
        ];

        $projects = Project::query()
            ->with(['client', 'manager', 'agency'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$filters['q']}%")
                    ->orWhere('number', 'like', "%{$filters['q']}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$filters['q']}%")),
            ))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['client'] !== '', fn ($q) => $q->where('client_id', (int) $filters['client']))
            ->when($filters['manager'] !== '', fn ($q) => $q->where('manager_id', (int) $filters['manager']))
            ->when($filters['agency'] !== '', fn ($q) => $q->where('agency_id', (int) $filters['agency']))
            ->orderByDesc('number')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Project $p): array => ProjectData::from($p));

        return Inertia::render('Projets/Index', [
            'projects' => $projects,
            'filters' => $filters,
            'options' => [
                'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Client $c): array => ['value' => (string) $c->id, 'label' => $c->name])->values(),
                'managers' => User::query()->whereIn('id', Project::query()->select('manager_id'))->orderBy('name')->get(['id', 'name'])
                    ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values(),
                'agencies' => Agency::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Agency $a): array => ['value' => (string) $a->id, 'label' => $a->name])->values(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projets/Form', ['project' => null, ...$this->formOptions()]);
    }

    public function store(SaveProjectRequest $request, CreateProject $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $project = $action->execute($request->projectData(), $user);
        $this->toast('Projet créé au statut « Étude ».');

        return to_route('projets.show', $project);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['client', 'manager', 'agency']);

        return Inertia::render('Projets/Show', [
            'project' => ProjectData::from($project, withDocuments: true),
            'chantiers' => $project->chantiers()->orderBy('code')->get()->map(fn (Chantier $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'status' => $c->status,
            ])->values(),
            'nextStatus' => $project->status->next(),
            'canAbandon' => in_array($project->status->getValue(), ProjectState::ABANDONABLE, true),
            // Devis et marché liés : disponibles avec les modules Devis et Marchés (V3).
            'quote' => null,
            'contract' => null,
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projets/Form', [
            'project' => ProjectData::from($project->load(['client', 'manager', 'agency'])),
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveProjectRequest $request, Project $project, UpdateProject $action): RedirectResponse
    {
        $action->execute($project, $request->projectData());
        $this->toast('Projet mis à jour.');

        return to_route('projets.show', $project);
    }

    public function advance(Project $project, AdvanceProject $action): RedirectResponse
    {
        $this->authorize('update', $project);
        $action->execute($project);
        $this->toast('Statut du projet mis à jour.');

        return back();
    }

    public function abandon(ReasonRequest $request, Project $project, AbandonProject $action): RedirectResponse
    {
        $this->authorize('update', $project);
        $action->execute($project, $request->string('reason')->toString());
        $this->toast('Projet abandonné.');

        return back();
    }

    public function storeDocument(UploadProjectDocumentRequest $request, Project $project): RedirectResponse
    {
        foreach ((array) $request->file('files', []) as $file) {
            $project->addMedia($file)->toMediaCollection('documents');
        }

        $this->toast('Document joint.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $c): array => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'managers' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values(),
            'agencies' => Agency::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Agency $a): array => ['value' => (string) $a->id, 'label' => $a->name])->values(),
        ];
    }
}
