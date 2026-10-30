<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chantiers;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Actions\AssignTeamMember;
use App\Domain\Chantiers\Actions\SaveChantier;
use App\Domain\Chantiers\Data\ChantierData;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\DailyReport;
use App\Domain\Chantiers\Support\PlanningCalculator;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chantiers\AssignMemberRequest;
use App\Http\Requests\Chantiers\SaveChantierRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Chantiers (M7) : liste filtrée par affectation (RG7.2), fiche (CA7.2) et équipe.
 */
final class ChantierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Chantier::class);
        $user = $this->user($request);
        $planning = PlanningCalculator::fromSettings();
        $today = now();

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
            'project' => $request->string('project')->toString(),
        ];

        $chantiers = Chantier::query()
            ->visibleTo($user)
            ->with(['project', 'team', 'tasks'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$filters['q']}%")->orWhere('code', 'like', "%{$filters['q']}%"),
            ))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['project'] !== '', fn ($q) => $q->where('project_id', (int) $filters['project']))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Chantier $c): array => ChantierData::from($c) + [
                'progress' => $planning->globalProgress($c->tasks),
                'late' => $c->tasks->filter(fn ($t): bool => $planning->isAlert($planning->health($t, $today)))->count(),
            ]);

        return Inertia::render('Chantiers/Index', [
            'chantiers' => $chantiers,
            'filters' => $filters,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $p): array => ['value' => (string) $p->id, 'label' => $p->name])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Chantier::class);

        return Inertia::render('Chantiers/Form', [
            'chantier' => null,
            'presetProject' => $request->integer('projet') ?: null,
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveChantierRequest $request, SaveChantier $action): RedirectResponse
    {
        $chantier = $action->create($request->chantierData());
        $this->toast('Chantier créé.');

        return to_route('chantiers.show', $chantier);
    }

    public function show(Request $request, Chantier $chantier): Response
    {
        $this->authorize('view', $chantier);
        $user = $this->user($request);
        $planning = PlanningCalculator::fromSettings();
        $today = now();

        $chantier->load(['project', 'team', 'tasks', 'budgetLines']);
        $tasks = $chantier->tasks;
        $lines = $chantier->budgetLines;
        $revised = $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->revised_amount, 0);
        $committed = $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->committed_amount, 0);
        $actual = $lines->reduce(fn (int $sum, BudgetLine $l): int => $sum + $l->actual_amount, 0);
        $photos = $chantier->getMedia('photos');

        return Inertia::render('Chantiers/Show', [
            'chantier' => ChantierData::from($chantier),
            'tasks' => $tasks->map(fn ($t): array => ChantierData::task($t, $planning, $today))->values(),
            'progress' => $planning->globalProgress($tasks),
            'budget' => [
                'lines' => $lines->count(),
                'revised' => $revised,
                'committed' => $committed,
                'actual' => $actual,
                'remaining' => $revised - $committed - $actual,
                'consumption' => $revised === 0 ? 0 : (($committed + $actual) / $revised) * 100,
            ],
            'photos' => $photos->sortByDesc('id')->values()->map(fn (Media $m): array => [
                'id' => $m->id,
                'url' => $m->getUrl(),
                'caption' => is_string($caption = $m->getCustomProperty('caption')) ? $caption : 'Photo',
                'date' => $m->created_at?->toDateString() ?? '',
            ]),
            'reports' => $chantier->dailyReports()->with('author')->get()->map(fn (DailyReport $r): array => [
                'id' => $r->id,
                'chantierId' => $r->chantier_id,
                'date' => $r->report_date->toDateString(),
                'author' => data_get($r, 'author.name', '—'),
                'works' => $r->works,
                'headcount' => $r->headcount,
                'materialsReceived' => $r->materials_received ?? '',
                'materialsUsed' => $r->materials_used ?? '',
                'incidents' => $r->incidents ?? 'Aucun.',
                'photos' => $photos->filter(fn (Media $m): bool => $m->getCustomProperty('report_id') === $r->id)->count(),
            ])->values(),
            'canEdit' => $user->can('update', $chantier),
            'readOnly' => $chantier->isReadOnly(),
            'members' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values(),
        ]);
    }

    public function edit(Chantier $chantier): Response
    {
        $this->authorize('update', $chantier);
        $chantier->load(['project', 'team']);

        return Inertia::render('Chantiers/Form', [
            'chantier' => ChantierData::from($chantier) + [
                'conducteurId' => $this->leadId($chantier, Chantier::ROLE_CONDUCTEUR),
                'chefId' => $this->leadId($chantier, Chantier::ROLE_CHEF),
            ],
            'presetProject' => null,
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveChantierRequest $request, Chantier $chantier, SaveChantier $action): RedirectResponse
    {
        $action->update($chantier, $request->chantierData());
        $this->toast('Chantier mis à jour.');

        return to_route('chantiers.show', $chantier);
    }

    public function assign(AssignMemberRequest $request, Chantier $chantier, AssignTeamMember $action): RedirectResponse
    {
        $action->execute($chantier, User::query()->findOrFail($request->integer('user_id')), $request->string('role')->toString());
        $this->toast('Membre affecté au chantier.');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    private function leadId(Chantier $chantier, string $role): ?int
    {
        $id = $chantier->team->first(fn (User $u): bool => data_get($u, 'pivot.role') === $role)?->id;

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $withRole = fn (RoleName $role) => User::query()->where('is_active', true)->role($role->value)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values();

        return [
            // RG4.3 : pas de nouveau chantier sur un projet clôturé ou abandonné.
            'projects' => Project::query()->whereNotIn('status', ['cloture', 'abandonne'])->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $p): array => ['value' => (string) $p->id, 'label' => $p->name])->values(),
            'conducteurs' => $withRole(RoleName::ConducteurTravaux),
            'chefs' => $withRole(RoleName::ChefChantier),
        ];
    }
}
