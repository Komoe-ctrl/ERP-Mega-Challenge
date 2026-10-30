<?php

declare(strict_types=1);

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Notifications\AppAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function dashUser(RoleName $role): User
{
    return userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

it('choisit la vue selon le profil : terrain, achats ou direction', function (RoleName $role, string $variant) {
    $this->actingAs(dashUser($role))->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('variant', $variant)->where('today', now()->toDateString()));
})->with([
    'chef de chantier' => [RoleName::ChefChantier, 'terrain'],
    'conducteur' => [RoleName::ConducteurTravaux, 'terrain'],
    'magasinier' => [RoleName::Magasinier, 'terrain'],
    'service achats' => [RoleName::ServiceAchatsBtp, 'achats'],
    'comptabilité' => [RoleName::Comptabilite, 'achats'],
    'direction générale' => [RoleName::DirectionGenerale, 'direction'],
    'direction technique' => [RoleName::DirectionTechnique, 'direction'],
    'finance' => [RoleName::Finance, 'direction'],
]);

it('RG7.2 : la vue terrain ne montre que les chantiers et alertes du profil', function () {
    Carbon::setTestNow('2026-11-20 08:00');
    $chef = dashUser(RoleName::ChefChantier);
    $mine = Chantier::factory()->create(['name' => 'Mon chantier', 'status' => 'en_cours']);
    $other = Chantier::factory()->create(['name' => 'Chantier voisin', 'status' => 'en_cours']);
    $mine->team()->attach($chef->id, ['role' => Chantier::ROLE_CHEF]);
    Task::factory()->create(['chantier_id' => $mine->id, 'name' => 'Mon retard', 'start_date' => '2026-10-01', 'end_date' => '2026-11-10', 'progress' => 50, 'last_update_on' => '2026-11-19']);
    Task::factory()->create(['chantier_id' => $other->id, 'name' => 'Retard voisin', 'start_date' => '2026-10-01', 'end_date' => '2026-11-10', 'progress' => 50]);
    PurchaseRequest::factory()->create(['chantier_id' => $mine->id, 'requester_id' => $chef->id]);
    PurchaseRequest::factory()->create(['chantier_id' => $other->id]);

    $this->actingAs($chef)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('terrain.chantiers', 1)
            ->where('terrain.chantiers.0.name', 'Mon chantier')
            ->where('terrain.primaryChantierId', $mine->id)
            ->has('terrain.requests', 1)
            ->has('terrain.alerts', 1)
            ->where('terrain.alerts.0.name', 'Mon retard')
            ->where('terrain.alerts.0.health', 'late'));
});

it('la vue achats liste les demandes validées à traiter avec leur montant', function () {
    $achats = dashUser(RoleName::ServiceAchatsBtp);
    PurchaseRequest::factory()->withStatus('validee')->withLines([10_000 => 5])->create();
    PurchaseRequest::factory()->withStatus('en_attente')->withLines([10_000 => 5])->create();

    $this->actingAs($achats)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('achats.toProcess', 1)->where('achats.toProcess.0.total', 50_000));
});

it('la vue direction consolide projets, chantiers, demandes à valider et budget', function () {
    $dg = dashUser(RoleName::DirectionGenerale);
    Project::factory()->withStatus('en_cours')->create();
    Project::factory()->withStatus('etude')->create();
    $site = Chantier::factory()->create(['status' => 'en_cours']);
    Chantier::factory()->create(['status' => 'suspendu']);
    BudgetLine::factory()->create(['chantier_id' => $site->id, 'revised_amount' => 100_000_000, 'committed_amount' => 50_000_000, 'actual_amount' => 35_000_000]);
    PurchaseRequest::factory()->withStatus('en_attente')->withLines([20_000 => 10])->create(['needs_direction' => true]);

    $this->actingAs($dg)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('direction.projects.inProgress', 1)
            ->where('direction.projects.total', 5) // les chantiers et la demande créent aussi leurs projets
            ->where('direction.chantiers.active', 1)
            ->where('direction.chantiers.suspended', 1)
            ->where('direction.pending.count', 1)
            ->where('direction.pending.amount', 200_000)
            ->where('direction.pending.queue.0.needsDirection', true)
            ->where('direction.budget.global.remaining', 15_000_000)
            ->where('direction.budget.global.level', 'warning')
            ->has('direction.budget.alerts', 1)
            ->where('direction.budget.alerts.0.level', 'warning')
            ->where('direction.budget.chart.0.consumed', 85_000_000));
});

it('ne consolide que les blocs que le profil a le droit de consulter', function () {
    $finance = dashUser(RoleName::Finance);
    Project::factory()->create();

    $this->actingAs($finance)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('variant', 'direction')
            ->where('direction.projects', null) // Finance n'a pas le droit sur les projets
            ->where('direction.chantiers', null)
            ->has('direction.budget')
            ->where('direction.pending.queue', [])); // lecture seule : pas de file à valider
});

it('affiche les dernières notifications de l\'utilisateur dans la vue direction', function () {
    $dg = dashUser(RoleName::DirectionGenerale);
    $dg->notify(new AppAlert('budget', 'Alerte budgétaire critique', 'Palmiers : 101 % consommé.', '/budget/1'));
    dashUser(RoleName::DirectionTechnique)->notify(new AppAlert('budget', 'Autre utilisateur', 'x', '/x'));

    $this->actingAs($dg)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('direction.notifications', 1)
            ->where('direction.notifications.0.title', 'Alerte budgétaire critique'));
});

it('reste accessible à un utilisateur sans rôle', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('variant', 'direction')->where('direction.projects', null));
});
