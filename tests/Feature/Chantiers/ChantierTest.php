<?php

declare(strict_types=1);

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

/** Direction technique : tous les chantiers en écriture, double authentification confirmée. */
function siteDirector(): User
{
    return userWithRole(RoleName::DirectionTechnique, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

/** Affecte un utilisateur à un chantier avec un rôle. */
function assignTo(Chantier $chantier, User $user, string $role): void
{
    $chantier->team()->attach($user->id, ['role' => $role]);
}

function chantierPayload(array $overrides = []): array
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $conducteur = User::factory()->create();
    $conducteur->assignRole(RoleName::ConducteurTravaux->value);
    $chef = User::factory()->create();
    $chef->assignRole(RoleName::ChefChantier->value);

    return $overrides + [
        'code' => 'CH-101',
        'name' => 'Résidence Test — Bloc C',
        'project_id' => Project::factory()->create()->id,
        'location' => 'Cocody, Abidjan',
        'start_date' => '2026-11-01',
        'end_date' => '2027-03-31',
        'status' => 'preparation',
        'conducteur_id' => $conducteur->id,
        'chef_id' => $chef->id,
    ];
}

function planningWorkbook(array $rows): UploadedFile
{
    $export = new class($rows) implements FromArray
    {
        public function __construct(private array $rows) {}

        public function array(): array
        {
            return $this->rows;
        }
    };

    return UploadedFile::fake()->createWithContent('planning.xlsx', Excel::raw($export, ExcelType::XLSX));
}

// ------------------------------------------------------------------ fiche (RG7.1)

it('crée un chantier avec son conducteur et son chef de chantier affectés', function () {
    $director = siteDirector();
    $payload = chantierPayload();

    $this->actingAs($director)->post(route('chantiers.store'), $payload)->assertRedirect();

    $chantier = Chantier::query()->where('code', 'CH-101')->firstOrFail();
    expect($chantier->status)->toBe('preparation')
        ->and($chantier->team()->wherePivot('role', Chantier::ROLE_CONDUCTEUR)->value('users.id'))->toBe($payload['conducteur_id'])
        ->and($chantier->team()->wherePivot('role', Chantier::ROLE_CHEF)->value('users.id'))->toBe($payload['chef_id']);
});

it('RG7.1 : refuse un code déjà utilisé ou mal formé', function () {
    $director = siteDirector();
    Chantier::factory()->create(['code' => 'CH-101']);

    $this->actingAs($director)->post(route('chantiers.store'), chantierPayload())
        ->assertSessionHasErrors(['code' => 'Ce code est déjà utilisé (RG7.1).']);
    $this->actingAs($director)->post(route('chantiers.store'), chantierPayload(['code' => 'XX-1']))
        ->assertSessionHasErrors('code');
});

it('exige un conducteur de travaux et un chef de chantier qui portent bien ces rôles', function () {
    $director = siteDirector();
    $payload = chantierPayload();
    $someone = User::factory()->create();

    $this->actingAs($director)->post(route('chantiers.store'), [...$payload, 'conducteur_id' => $someone->id])
        ->assertSessionHasErrors('conducteur_id');
    $this->actingAs($director)->post(route('chantiers.store'), [...$payload, 'chef_id' => $someone->id])
        ->assertSessionHasErrors('chef_id');
});

it('RG4.3 : refuse de créer un chantier sur un projet clôturé ou abandonné', function (string $status) {
    $project = Project::factory()->withStatus($status)->create();

    $this->actingAs(siteDirector())->post(route('chantiers.store'), chantierPayload(['project_id' => $project->id]))
        ->assertSessionHasErrors('project_id');
    expect(Chantier::query()->count())->toBe(0);
})->with(['cloture', 'abandonne']);

it('modifie un chantier et remplace ses responsables sans changer de projet', function () {
    $director = siteDirector();
    $chantier = Chantier::factory()->create(['code' => 'CH-050']);
    $payload = chantierPayload(['code' => 'CH-050', 'name' => 'Nom modifié du chantier']);

    $this->actingAs($director)->put(route('chantiers.update', $chantier), $payload)->assertRedirect();

    $chantier->refresh();
    expect($chantier->name)->toBe('Nom modifié du chantier')
        ->and($chantier->project_id)->not->toBe($payload['project_id'])
        ->and($chantier->team()->wherePivot('role', Chantier::ROLE_CONDUCTEUR)->value('users.id'))->toBe($payload['conducteur_id']);
});

it('RG4.3 : un chantier clôturé, ou dont le projet est clôturé, refuse toute écriture', function () {
    $director = siteDirector();
    $closed = Chantier::factory()->create(['status' => 'cloture']);
    $ofClosedProject = Chantier::factory()->create(['project_id' => Project::factory()->withStatus('cloture')->create()->id]);

    foreach ([$closed, $ofClosedProject] as $chantier) {
        $this->actingAs($director)->get(route('chantiers.edit', $chantier))->assertForbidden();
        $this->actingAs($director)->post(route('chantiers.tasks.store', $chantier), ['name' => 'Tâche', 'start_date' => '2026-11-01', 'end_date' => '2026-11-05', 'weight' => 1])->assertForbidden();
        $this->actingAs($director)->post(route('chantiers.avancement.store', $chantier), ['entries' => []])->assertForbidden();
        $this->actingAs($director)->get(route('chantiers.show', $chantier))->assertOk();
    }
});

// ------------------------------------------------------------------ RG7.2 affectations

it('RG7.2 : un conducteur ou un chef ne voit que ses chantiers', function (RoleName $role, string $pivotRole) {
    $user = userWithRole($role);
    $mine = Chantier::factory()->create(['name' => 'Mon chantier']);
    $other = Chantier::factory()->create(['name' => 'Chantier d\'un collègue']);
    assignTo($mine, $user, $pivotRole);

    $this->actingAs($user)->get(route('chantiers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('chantiers.total', 1)->where('chantiers.data.0.name', 'Mon chantier'));
    $this->actingAs($user)->get(route('chantiers.show', $mine))->assertOk();
    $this->actingAs($user)->get(route('chantiers.show', $other))->assertForbidden();
    $this->actingAs($user)->get(route('chantiers.avancement', $other))->assertForbidden();
    $this->actingAs($user)->post(route('chantiers.rapport.store', $other), ['works' => 'Test', 'headcount' => 3])->assertForbidden();
})->with([
    'conducteur' => [RoleName::ConducteurTravaux, Chantier::ROLE_CONDUCTEUR],
    'chef de chantier' => [RoleName::ChefChantier, Chantier::ROLE_CHEF],
]);

it('RG7.2 : les autres profils voient tous les chantiers', function () {
    Chantier::factory()->count(3)->create();

    $this->actingAs(userWithRole(RoleName::ServiceAchatsBtp))->get(route('chantiers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('chantiers.total', 3));
    $this->actingAs(siteDirector())->get(route('chantiers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('chantiers.total', 3));
});

it('affecte un membre à un chantier, une seule fois par rôle', function () {
    $director = siteDirector();
    $chantier = Chantier::factory()->create();
    $storeman = User::factory()->create();

    $this->actingAs($director)->post(route('chantiers.team.assign', $chantier), ['user_id' => $storeman->id, 'role' => 'magasinier'])->assertRedirect();
    expect($chantier->team()->wherePivot('role', 'magasinier')->count())->toBe(1);

    $this->actingAs($director)->post(route('chantiers.team.assign', $chantier), ['user_id' => $storeman->id, 'role' => 'magasinier'])
        ->assertSessionHasErrors('user_id');
    $this->actingAs($director)->post(route('chantiers.team.assign', $chantier), ['user_id' => $storeman->id, 'role' => 'pirate'])
        ->assertSessionHasErrors('role');
});

it('refuse les chantiers aux profils sans droit (403)', function () {
    $user = userWithRole(RoleName::Comptabilite);
    $chantier = Chantier::factory()->create();

    $this->actingAs($user)->get(route('chantiers.index'))->assertForbidden();
    $this->actingAs($user)->get(route('chantiers.show', $chantier))->assertForbidden();
    $this->actingAs($user)->post(route('chantiers.store'), chantierPayload())->assertForbidden();
});

it('laisse un profil en lecture seule consulter sans écrire', function () {
    $achats = userWithRole(RoleName::ServiceAchatsBtp);
    $chantier = Chantier::factory()->create();

    $this->actingAs($achats)->get(route('chantiers.show', $chantier))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    $this->actingAs($achats)->post(route('chantiers.tasks.store', $chantier), ['name' => 'Tâche', 'start_date' => '2026-11-01', 'end_date' => '2026-11-05', 'weight' => 1])->assertForbidden();
});

// ------------------------------------------------------------------ planning (RG7.3)

it('RG7.3 : ajoute une tâche et un jalon au planning', function () {
    $director = siteDirector();
    $chantier = Chantier::factory()->create();

    $this->actingAs($director)->post(route('chantiers.tasks.store', $chantier), [
        'name' => 'Fondations', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'weight' => 10,
    ])->assertRedirect();
    $this->actingAs($director)->post(route('chantiers.tasks.store', $chantier), [
        'name' => 'Réception du gros œuvre', 'start_date' => '2026-12-20', 'weight' => 0, 'is_milestone' => true,
    ])->assertRedirect();

    $tasks = $chantier->tasks()->get();
    expect($tasks)->toHaveCount(2)
        ->and($tasks[1]->is_milestone)->toBeTrue()
        ->and($tasks[1]->end_date->toDateString())->toBe('2026-12-20')
        ->and($tasks[0]->progress)->toBe(0);
});

it('valide la saisie d\'une tâche', function (array $override, string $field) {
    $chantier = Chantier::factory()->create();

    $this->actingAs(siteDirector())->post(route('chantiers.tasks.store', $chantier), $override + [
        'name' => 'Fondations', 'start_date' => '2026-11-10', 'end_date' => '2026-11-30', 'weight' => 10,
    ])->assertSessionHasErrors($field);
})->with([
    'fin avant début' => [['end_date' => '2026-11-01'], 'end_date'],
    'nom trop court' => [['name' => 'ab'], 'name'],
    'pondération hors bornes' => [['weight' => 150], 'weight'],
]);

it('RG7.3 : importe le planning depuis Excel (dates Excel, texte JJ/MM/AAAA et jalon)', function () {
    $chantier = Chantier::factory()->create();
    $file = planningWorkbook([
        ['Tâche', 'Début', 'Fin prévue', 'Pondération', 'Jalon'],
        ['Terrassement', '01/11/2026', '15/11/2026', 5, 'non'],
        ['Fondations', '2026-11-16', '2026-12-20', 12, 'non'],
        ['Réception des fondations', '20/12/2026', '20/12/2026', 0, 'oui'],
    ]);

    $this->actingAs(siteDirector())->post(route('chantiers.planning.import', $chantier), ['file' => $file])->assertSessionHasNoErrors();

    $tasks = $chantier->tasks()->get();
    expect($tasks)->toHaveCount(3)
        ->and($tasks[0]->start_date->toDateString())->toBe('2026-11-01')
        ->and($tasks[1]->weight)->toBe(12)
        ->and($tasks[2]->is_milestone)->toBeTrue();
});

it('refuse tout l\'import si une ligne est invalide, en indiquant la ligne', function () {
    $chantier = Chantier::factory()->create();
    $file = planningWorkbook([
        ['Tâche', 'Début', 'Fin prévue', 'Pondération'],
        ['Terrassement', '01/11/2026', '15/11/2026', 5],
        ['Fondations', '20/12/2026', '16/11/2026', 12],
    ]);

    $this->actingAs(siteDirector())->post(route('chantiers.planning.import', $chantier), ['file' => $file])
        ->assertSessionHasErrors(['file' => 'Ligne 3 : la fin précède le début.']);

    expect($chantier->tasks()->count())->toBe(0);
});

it('refuse un import qui n\'est pas un fichier Excel ou qui est vide', function () {
    $chantier = Chantier::factory()->create();

    $this->actingAs(siteDirector())->post(route('chantiers.planning.import', $chantier), ['file' => UploadedFile::fake()->create('planning.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
    $this->actingAs(siteDirector())->post(route('chantiers.planning.import', $chantier), ['file' => planningWorkbook([['Tâche', 'Début', 'Fin prévue', 'Pondération']])])
        ->assertSessionHasErrors('file');
});

// ------------------------------------------------------------------ fiche (CA7.2) et journal

it('CA7.2 : la fiche affiche avancement, tâches en retard, budget engagé et restant, photos', function () {
    Storage::fake('public');
    $chantier = Chantier::factory()->create();
    Task::factory()->create(['chantier_id' => $chantier->id, 'progress' => 100, 'weight' => 10, 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
    Task::factory()->create(['chantier_id' => $chantier->id, 'progress' => 20, 'weight' => 10, 'start_date' => '2026-01-01', 'end_date' => '2026-02-01']);
    BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 100_000_000, 'committed_amount' => 30_000_000, 'actual_amount' => 20_000_000]);
    $chantier->addMedia(UploadedFile::fake()->image('coulage.jpg'))->withCustomProperties(['caption' => 'Coulage plancher'])->toMediaCollection('photos');

    $this->actingAs(siteDirector())->get(route('chantiers.show', $chantier))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chantiers/Show')
            ->where('progress', 60)
            ->where('tasks.1.health', 'late')
            ->where('budget.committed', 30_000_000)
            ->where('budget.remaining', 50_000_000)
            ->where('budget.consumption', 50)
            ->where('photos.0.caption', 'Coulage plancher'));
});

it('trace la création d\'un chantier au journal', function () {
    $director = siteDirector();
    $this->actingAs($director)->post(route('chantiers.store'), chantierPayload());

    $entry = Activity::query()->where('subject_type', Chantier::class)->where('event', 'created')->firstOrFail();
    expect($entry->causer_id)->toBe($director->id);
});

it('ne permet jamais de supprimer un chantier', function () {
    $deletable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true) && str_starts_with($r->uri(), 'chantiers'));

    expect($deletable)->toBeEmpty()->and(siteDirector()->can('delete', Chantier::factory()->create()))->toBeFalse();
});
