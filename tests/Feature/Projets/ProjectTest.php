<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Agency;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Clients\Models\Client;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/** Direction technique : écriture sur les projets, double authentification confirmée. */
function projectWriter(): User
{
    return userWithRole(RoleName::DirectionTechnique, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

function projectPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Résidence Les Acacias',
        'client_id' => Client::factory()->create()->id,
        'location' => 'Cocody, Abidjan',
        'manager_id' => User::factory()->create()->id,
        'agency_id' => Agency::factory()->create()->id,
        'budget' => 250_000_000,
        'start_date' => '2026-12-01',
        'end_date' => '2027-08-31',
    ];
}

/** Chantier prêt au démarrage (RG4.2) : un conducteur affecté et un budget. */
function readyChantier(Project $project): Chantier
{
    $chantier = Chantier::factory()->create(['project_id' => $project->id]);
    $chantier->team()->attach(User::factory()->create()->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    BudgetLine::factory()->create(['chantier_id' => $chantier->id]);

    return $chantier;
}

// ---------------------------------------------------------------- liste (CA4)

it('liste les projets paginés et se filtre par statut, client, responsable et agence (CA4)', function () {
    $user = projectWriter();
    $client = Client::factory()->create();
    $manager = User::factory()->create();
    $agency = Agency::factory()->create();
    Project::factory()->withStatus('en_cours')->create(['client_id' => $client->id, 'manager_id' => $manager->id, 'agency_id' => $agency->id]);
    Project::factory()->count(20)->create();

    $this->actingAs($user)->get(route('projets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projets/Index')
            ->has('projects.data', 15)
            ->where('projects.total', 21));

    foreach ([['status', 'en_cours'], ['client', $client->id], ['manager', $manager->id], ['agency', $agency->id]] as [$filter, $value]) {
        $this->actingAs($user)->get(route('projets.index', [$filter => $value]))
            ->assertInertia(fn (Assert $page) => $page->where('projects.total', 1)->where('projects.data.0.clientName', $client->name));
    }
});

it('recherche un projet par nom, numéro ou client', function () {
    $user = projectWriter();
    Project::factory()->create(['name' => 'Entrepôt logistique Yopougon']);
    Project::factory()->count(3)->create();

    $this->actingAs($user)->get(route('projets.index', ['q' => 'Yopougon']))
        ->assertInertia(fn (Assert $page) => $page->where('projects.total', 1));
});

// ---------------------------------------------------------------- création

it('crée un projet au statut Étude avec un numéro séquentiel PRJ-AAAA-NNN', function () {
    $user = projectWriter();

    $this->actingAs($user)->post(route('projets.store'), projectPayload())->assertRedirect();
    $this->actingAs($user)->post(route('projets.store'), projectPayload(['name' => 'Second projet test']))->assertRedirect();

    $year = now()->year;
    $projects = Project::query()->orderBy('id')->get();
    expect($projects->pluck('number')->all())->toBe(["PRJ-{$year}-001", "PRJ-{$year}-002"])
        ->and($projects[0]->status->getValue())->toBe('etude')
        ->and($projects[0]->created_by)->toBe($user->id)
        ->and($projects[0]->budget)->toBe(250_000_000);
});

it('valide la saisie côté serveur', function (array $override, string $field) {
    $this->actingAs(projectWriter())->post(route('projets.store'), projectPayload($override))->assertSessionHasErrors($field);

    expect(Project::query()->count())->toBe(0);
})->with([
    'nom trop court' => [['name' => 'Abc'], 'name'],
    'budget décimal' => [['budget' => '1000.5'], 'budget'],
    'budget nul' => [['budget' => 0], 'budget'],
    'fin avant le début' => [['start_date' => '2027-01-10', 'end_date' => '2027-01-01'], 'end_date'],
    'client inconnu' => [['client_id' => 9999], 'client_id'],
]);

it('refuse un responsable désactivé ou une agence désactivée', function () {
    $inactive = User::factory()->create(['is_active' => false]);
    $closedAgency = Agency::factory()->create(['is_active' => false]);

    $this->actingAs(projectWriter())->post(route('projets.store'), projectPayload(['manager_id' => $inactive->id]))
        ->assertSessionHasErrors(['manager_id' => 'Le responsable doit être un utilisateur actif.']);
    $this->actingAs(projectWriter())->post(route('projets.store'), projectPayload(['agency_id' => $closedAgency->id]))
        ->assertSessionHasErrors('agency_id');
});

it('modifie un projet sans toucher à son statut', function () {
    $project = Project::factory()->withStatus('devis')->create();

    $this->actingAs(projectWriter())->put(route('projets.update', $project), projectPayload(['name' => 'Nom modifié du projet']))
        ->assertRedirect(route('projets.show', $project));

    $project->refresh();
    expect($project->name)->toBe('Nom modifié du projet')->and($project->status->getValue())->toBe('devis');
});

// ---------------------------------------------------------------- statuts

it('fait avancer un projet dans son parcours : Étude → Devis → Appel d\'offres → Marché → Préparation', function () {
    $user = projectWriter();
    $project = Project::factory()->create();

    foreach (['devis', 'appel_offres', 'marche', 'preparation'] as $expected) {
        $this->actingAs($user)->post(route('projets.advance', $project))->assertRedirect();
        expect($project->refresh()->status->getValue())->toBe($expected);
    }
});

it('RG4.2 : le passage à « En cours » exige un chantier avec un budget et un responsable', function () {
    $user = projectWriter();
    $project = Project::factory()->withStatus('preparation')->create();

    $this->actingAs($user)->post(route('projets.advance', $project))->assertSessionHasErrors('status');
    expect($project->refresh()->status->getValue())->toBe('preparation');

    // Chantier sans budget, puis avec budget mais sans responsable : toujours refusé.
    $chantier = Chantier::factory()->create(['project_id' => $project->id]);
    $this->actingAs($user)->post(route('projets.advance', $project))->assertSessionHasErrors('status');

    BudgetLine::factory()->create(['chantier_id' => $chantier->id]);
    $this->actingAs($user)->post(route('projets.advance', $project))->assertSessionHasErrors('status');

    $chantier->team()->attach(User::factory()->create()->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    $this->actingAs($user)->post(route('projets.advance', $project))->assertSessionHasNoErrors();
    expect($project->refresh()->status->getValue())->toBe('en_cours');
});

it('RG4.2 : un chef de chantier seul ne vaut pas responsable', function () {
    $project = Project::factory()->withStatus('preparation')->create();
    $chantier = Chantier::factory()->create(['project_id' => $project->id]);
    BudgetLine::factory()->create(['chantier_id' => $chantier->id]);
    $chantier->team()->attach(User::factory()->create()->id, ['role' => Chantier::ROLE_CHEF]);

    $this->actingAs(projectWriter())->post(route('projets.advance', $project))->assertSessionHasErrors('status');
});

it('refuse d\'avancer au-delà de Clôturé', function () {
    $project = Project::factory()->withStatus('cloture')->create();

    $this->actingAs(projectWriter())->post(route('projets.advance', $project))->assertForbidden();
});

// ---------------------------------------------------------------- abandon (RG4.1)

it('RG4.1 : abandonne un projet avant « Marché », avec un motif obligatoire', function (string $status) {
    $user = projectWriter();
    $project = Project::factory()->withStatus($status)->create();

    $this->actingAs($user)->post(route('projets.abandon', $project), [])->assertSessionHasErrors(['reason' => 'Le motif est obligatoire.']);
    expect($project->refresh()->status->getValue())->toBe($status);

    $this->actingAs($user)->post(route('projets.abandon', $project), ['reason' => 'Appel d\'offres perdu'])->assertRedirect();
    $project->refresh();
    expect($project->status->getValue())->toBe('abandonne')->and($project->abandon_reason)->toBe('Appel d\'offres perdu');
})->with(['etude', 'devis', 'appel_offres']);

it('RG4.1 : refuse l\'abandon à partir de « Marché »', function (string $status) {
    $project = Project::factory()->withStatus($status)->create();

    $this->actingAs(projectWriter())->post(route('projets.abandon', $project), ['reason' => 'Trop tard'])->assertSessionHasErrors('status');
    expect($project->refresh()->status->getValue())->toBe($status);
})->with(['marche', 'preparation', 'en_cours', 'reception']);

// ---------------------------------------------------------------- lecture seule (RG4.3)

it('RG4.3 : un projet clôturé ou abandonné refuse toute modification', function (string $status) {
    $user = projectWriter();
    $project = Project::factory()->withStatus($status)->create();

    $this->actingAs($user)->get(route('projets.edit', $project))->assertForbidden();
    $this->actingAs($user)->put(route('projets.update', $project), projectPayload())->assertForbidden();
    $this->actingAs($user)->post(route('projets.documents.store', $project), ['files' => [UploadedFile::fake()->create('plan.pdf', 10)]])->assertForbidden();
})->with(['cloture', 'abandonne']);

it('RG4.3 : les chantiers d\'un projet clôturé passent en lecture seule', function () {
    $user = projectWriter();
    $project = Project::factory()->withStatus('en_cours')->create();
    $chantier = Chantier::factory()->create(['project_id' => $project->id]);

    expect($user->can('update', $chantier))->toBeTrue();

    $project->forceFill(['status' => 'cloture'])->saveQuietly();

    expect($user->can('update', $chantier->fresh()))->toBeFalse();
});

// ---------------------------------------------------------------- documents

it('joint des documents de 20 Mo maximum', function () {
    Storage::fake('public');
    $project = Project::factory()->create();

    $this->actingAs(projectWriter())->post(route('projets.documents.store', $project), [
        'files' => [UploadedFile::fake()->create('plans.pdf', 8_000, 'application/pdf')],
    ])->assertRedirect();

    expect($project->getMedia('documents'))->toHaveCount(1);

    $this->actingAs(projectWriter())->post(route('projets.documents.store', $project), [
        'files' => [UploadedFile::fake()->create('trop-lourd.pdf', 21_000, 'application/pdf')],
    ])->assertSessionHasErrors('files.0');

    expect($project->fresh()?->getMedia('documents'))->toHaveCount(1);
});

it('affiche la fiche avec ses chantiers et ses documents', function () {
    Storage::fake('public');
    $project = Project::factory()->create();
    Chantier::factory()->create(['project_id' => $project->id, 'code' => 'CH-777']);
    $project->addMedia(UploadedFile::fake()->create('etude.pdf', 100, 'application/pdf'))->toMediaCollection('documents');

    $this->actingAs(projectWriter())->get(route('projets.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projets/Show')
            ->where('project.status', 'etude')
            ->where('nextStatus', 'devis')
            ->where('canAbandon', true)
            ->where('chantiers.0.code', 'CH-777')
            ->where('project.documents.0.name', 'etude.pdf'));
});

// ---------------------------------------------------------------- droits et journal

it('refuse les projets aux profils sans droit (403), en lecture comme en écriture', function (RoleName $role) {
    $user = userWithRole($role);
    $project = Project::factory()->create();

    $this->actingAs($user)->get(route('projets.index'))->assertForbidden();
    $this->actingAs($user)->post(route('projets.store'), projectPayload())->assertForbidden();
    $this->actingAs($user)->post(route('projets.advance', $project))->assertForbidden();
    $this->actingAs($user)->post(route('projets.abandon', $project), ['reason' => 'x y z'])->assertForbidden();

    expect($project->refresh()->status->getValue())->toBe('etude');
})->with([RoleName::ChefChantier, RoleName::Magasinier, RoleName::ServiceAchatsBtp, RoleName::Comptabilite]);

it('laisse la direction générale consulter sans modifier', function () {
    $dg = userWithRole(RoleName::DirectionGenerale, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $project = Project::factory()->create();

    $this->actingAs($dg)->get(route('projets.index'))->assertOk();
    $this->actingAs($dg)->get(route('projets.show', $project))->assertOk();
    $this->actingAs($dg)->post(route('projets.store'), projectPayload())->assertForbidden();
});

it('ne permet jamais de supprimer un projet', function () {
    $deletable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true) && str_starts_with($r->uri(), 'projets'));

    expect($deletable)->toBeEmpty()
        ->and(projectWriter()->can('delete', Project::factory()->create()))->toBeFalse();
});

it('trace au journal la création et les changements de statut avec leurs valeurs avant et après', function () {
    $user = projectWriter();
    $project = Project::factory()->create();
    $this->actingAs($user)->post(route('projets.advance', $project));

    $entry = Activity::query()->where('subject_type', Project::class)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->causer_id)->toBe($user->id)
        ->and($entry->attribute_changes?->get('old'))->toMatchArray(['status' => 'etude'])
        ->and($entry->attribute_changes?->get('attributes'))->toMatchArray(['status' => 'devis'])
        ->and(Activity::query()->where('subject_type', Project::class)->where('event', 'created')->exists())->toBeTrue();
});
