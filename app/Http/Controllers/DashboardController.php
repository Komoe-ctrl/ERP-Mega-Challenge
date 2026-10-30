<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Support\BudgetSummary;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Chantiers\Support\PlanningCalculator;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord par profil : terrain (mes chantiers, mes demandes), achats
 * (demandes à traiter) ou direction (consolidé multi-chantiers). Chaque bloc
 * ne contient que ce que le profil a le droit de consulter, et un utilisateur
 * de terrain ne voit que ses chantiers (RG7.2).
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $variant = $this->variant($user);

        return Inertia::render('Dashboard', [
            'variant' => $variant,
            'today' => now()->toDateString(),
            'terrain' => $variant === 'terrain' ? $this->terrain($user) : null,
            'achats' => $variant === 'achats' ? $this->achats($user) : null,
            'direction' => $variant === 'direction' ? $this->direction($user) : null,
        ]);
    }

    /** @return 'terrain'|'achats'|'direction' */
    private function variant(User $user): string
    {
        return match (true) {
            $user->hasAnyRole([RoleName::ChefChantier->value, RoleName::ConducteurTravaux->value, RoleName::Magasinier->value]) => 'terrain',
            $user->hasAnyRole([RoleName::ServiceAchatsBtp->value, RoleName::Comptabilite->value]) => 'achats',
            default => 'direction',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function terrain(User $user): array
    {
        $chantiers = $user->can('chantiers.view')
            ? Chantier::query()->visibleTo($user)->whereIn('status', ['preparation', 'en_cours', 'suspendu'])->with('tasks')->orderBy('code')->limit(6)->get()
            : new Collection;

        return [
            'chantiers' => $chantiers->map(fn (Chantier $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'status' => $c->status,
                'location' => $c->location,
                'endDate' => $c->end_date->toDateString(),
            ])->values(),
            'primaryChantierId' => $chantiers->first()?->id,
            'requests' => $user->can('achats.view')
                ? PurchaseRequest::query()->where('requester_id', $user->id)->with(['lines.article'])->latest('id')->limit(5)->get()
                    ->map(fn (PurchaseRequest $r): array => ['id' => $r->id, 'number' => $r->number, 'status' => $r->status->getValue(), 'total' => $r->computeTotal()])->values()
                : [],
            'alerts' => $this->taskAlerts($chantiers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function achats(User $user): array
    {
        $toProcess = $user->can('achats.view')
            ? PurchaseRequest::query()->where('status', 'validee')->with(['chantier', 'lines.article'])->orderBy('id')->limit(20)->get()
                ->map(fn (PurchaseRequest $r): array => [
                    'id' => $r->id,
                    'number' => $r->number,
                    'chantierName' => data_get($r, 'chantier.name', ''),
                    'total' => $r->computeTotal(),
                ])->values()
            : new Collection;

        return ['toProcess' => $toProcess];
    }

    /**
     * @return array<string, mixed>
     */
    private function direction(User $user): array
    {
        $canProjects = $user->can('projets.view');
        $canChantiers = $user->can('chantiers.view');
        $canBudget = $user->can('budget.view');
        $canAchats = $user->can('achats.view');

        $chantiers = $canChantiers || $canBudget
            ? Chantier::query()->visibleTo($user)->with(['tasks', 'budgetLines'])->get()
            : new Collection;
        $budgeted = $canBudget ? $chantiers->filter(fn (Chantier $c): bool => $c->budgetLines->isNotEmpty())->values() : new Collection;
        $summaries = $budgeted->map(fn (Chantier $c): array => ['chantier' => $c, 'summary' => BudgetSummary::fromLines($c->budgetLines)]);
        $global = BudgetSummary::fromLines(BudgetLine::query()->whereIn('chantier_id', $budgeted->modelKeys())->get());

        $pending = $canAchats
            ? PurchaseRequest::query()->where('status', 'en_attente')->with(['chantier', 'requester', 'lines.article'])->orderBy('id')->get()
            : new Collection;

        return [
            'projects' => $canProjects ? [
                'inProgress' => Project::query()->where('status', 'en_cours')->count(),
                'total' => Project::query()->count(),
            ] : null,
            'chantiers' => $canChantiers ? [
                'active' => $chantiers->where('status', 'en_cours')->count(),
                'suspended' => $chantiers->where('status', 'suspendu')->count(),
            ] : null,
            'pending' => $canAchats ? [
                'count' => $pending->count(),
                'amount' => $pending->sum(fn (PurchaseRequest $r): int => $r->computeTotal()),
                'queue' => $user->can('achats.validate')
                    ? $pending->map(fn (PurchaseRequest $r): array => [
                        'id' => $r->id,
                        'number' => $r->number,
                        'chantierName' => data_get($r, 'chantier.name', ''),
                        'requester' => data_get($r, 'requester.name', '—'),
                        'needsDirection' => $r->needs_direction,
                    ])->values()
                    : [],
            ] : null,
            'budget' => $canBudget ? [
                'global' => $global->toArray(),
                'chart' => $summaries->map(fn (array $b): array => [
                    'code' => $b['chantier']->code,
                    'revised' => $b['summary']->revised,
                    'consumed' => $b['summary']->committed + $b['summary']->actual,
                ])->values(),
                'alerts' => $summaries->filter(fn (array $b): bool => $b['summary']->level() !== 'ok')->map(fn (array $b): array => [
                    'chantierId' => $b['chantier']->id,
                    'name' => $b['chantier']->name,
                    'consumption' => $b['summary']->consumption(),
                    'level' => $b['summary']->level(),
                ])->values(),
            ] : null,
            'taskAlerts' => $canChantiers ? $this->taskAlerts($chantiers) : [],
            'notifications' => $user->notifications()->latest()->limit(4)->get()->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'title' => data_get($n->data, 'title', ''),
                'body' => data_get($n->data, 'body', ''),
                'href' => data_get($n->data, 'href', '/notifications'),
            ])->values(),
        ];
    }

    /**
     * Tâches en retard, à risque ou non renseignées des chantiers donnés, calculées par le serveur.
     *
     * @param  Collection<int, Chantier>  $chantiers
     * @return list<array{id: int, name: string, chantierId: int, chantierName: string, health: string}>
     */
    private function taskAlerts(Collection $chantiers): array
    {
        $planning = PlanningCalculator::fromSettings();
        $today = now();
        $alerts = [];

        foreach ($chantiers->whereIn('status', ['preparation', 'en_cours']) as $chantier) {
            foreach ($chantier->tasks as $task) {
                /** @var Task $task */
                $health = $planning->health($task, $today);

                if ($planning->isAlert($health)) {
                    $alerts[] = ['id' => $task->id, 'name' => $task->name, 'chantierId' => $chantier->id, 'chantierName' => $chantier->name, 'health' => $health];
                }
            }
        }

        return $alerts;
    }
}
