<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Setting;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\DailyReport;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** Chef de chantier affecté à un chantier, avec une tâche. */
function fieldSetup(): array
{
    $chef = userWithRole(RoleName::ChefChantier);
    $chantier = Chantier::factory()->create();
    $chantier->team()->attach($chef->id, ['role' => Chantier::ROLE_CHEF]);
    $task = Task::factory()->create(['chantier_id' => $chantier->id, 'progress' => 10, 'last_update_on' => '2026-10-01']);

    return [$chef, $chantier, $task];
}

// ------------------------------------------------------------------ avancement (RG7.4)

it('RG7.4 : enregistre l\'avancement en %, avec commentaire et photos, et garde l\'historique', function () {
    Storage::fake('public');
    Carbon::setTestNow('2026-11-10');
    [$chef, $chantier, $task] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.avancement.store', $chantier), [
        'entries' => [['task_id' => $task->id, 'progress' => 45, 'comment' => 'Pluie lundi', 'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]],
    ])->assertRedirect(route('chantiers.show', $chantier));

    $task->refresh();
    expect($task->progress)->toBe(45)
        ->and($task->comment)->toBe('Pluie lundi')
        ->and($task->last_update_on->toDateString())->toBe('2026-11-10')
        ->and($task->updates()->first()->only(['progress_before', 'progress_after', 'user_id']))->toBe(['progress_before' => 10, 'progress_after' => 45, 'user_id' => $chef->id])
        ->and($chantier->getMedia('photos'))->toHaveCount(2)
        ->and($chantier->getMedia('photos')[0]->getCustomProperty('caption'))->toBe($task->name);
});

it('RG7.7 : une mise à jour remet la date de dernière mise à jour, une absence de changement ne la touche pas', function () {
    Carbon::setTestNow('2026-11-10');
    [$chef, $chantier, $task] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.avancement.store', $chantier), [
        'entries' => [['task_id' => $task->id, 'progress' => 10]],
    ])->assertRedirect();
    expect($task->refresh()->last_update_on->toDateString())->toBe('2026-10-01')
        ->and($task->updates()->count())->toBe(0);
});

it('valide l\'avancement : entre 0 et 100 %, entier', function (mixed $value) {
    [$chef, $chantier, $task] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.avancement.store', $chantier), [
        'entries' => [['task_id' => $task->id, 'progress' => $value]],
    ])->assertSessionHasErrors('entries.0.progress');
    expect($task->refresh()->progress)->toBe(10);
})->with([150, -5, '12,5']);

it('ignore les jalons et les tâches d\'un autre chantier', function () {
    [$chef, $chantier] = fieldSetup();
    $milestone = Task::factory()->create(['chantier_id' => $chantier->id, 'is_milestone' => true]);
    $foreign = Task::factory()->create(['progress' => 0]);

    $this->actingAs($chef)->post(route('chantiers.avancement.store', $chantier), [
        'entries' => [['task_id' => $milestone->id, 'progress' => 100], ['task_id' => $foreign->id, 'progress' => 100]],
    ])->assertRedirect();

    expect($milestone->refresh()->progress)->toBe(0)->and($foreign->refresh()->progress)->toBe(0);
});

it('refuse les photos trop lourdes ou qui ne sont pas des images', function () {
    [$chef, $chantier, $task] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.avancement.store', $chantier), [
        'entries' => [['task_id' => $task->id, 'progress' => 20, 'photos' => [UploadedFile::fake()->create('virus.exe', 10)]]],
    ])->assertSessionHasErrors('entries.0.photos.0');
});

it('affiche l\'écran de saisie avec l\'état calculé par le serveur', function () {
    [$chef, $chantier] = fieldSetup();

    $this->actingAs($chef)->get(route('chantiers.avancement', $chantier))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chantiers/Avancement')
            ->has('tasks', 1)
            ->where('thresholds.risk', 15)
            ->has('tasks.0.health')
            ->has('tasks.0.theoretical'));
});

// ------------------------------------------------------------------ rapport journalier (RG7.9)

function reportPayload(array $overrides = []): array
{
    return $overrides + [
        'works' => 'Coulage du plancher haut R+1',
        'headcount' => 34,
        'materials_received' => '12 t de fer HA 12',
        'materials_used' => '9 m³ de béton',
        'incidents' => 'Pluie de 14 h à 16 h',
    ];
}

it('RG7.9 : enregistre le rapport journalier avec effectif, matériaux, incidents et photos', function () {
    Storage::fake('public');
    Carbon::setTestNow('2026-11-10');
    [$chef, $chantier] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.rapport.store', $chantier), reportPayload(['photos' => [UploadedFile::fake()->image('r.jpg')]]))
        ->assertRedirect(route('chantiers.show', $chantier));

    $report = DailyReport::query()->firstOrFail();
    expect($report->report_date->toDateString())->toBe('2026-11-10')
        ->and($report->author_id)->toBe($chef->id)
        ->and($report->headcount)->toBe(34)
        ->and($report->incidents)->toBe('Pluie de 14 h à 16 h')
        ->and($chantier->getMedia('photos'))->toHaveCount(1)
        ->and($chantier->getMedia('photos')[0]->getCustomProperty('report_id'))->toBe($report->id);
});

it('n\'accepte qu\'un rapport par chantier et par jour', function () {
    Carbon::setTestNow('2026-11-10');
    [$chef, $chantier] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.rapport.store', $chantier), reportPayload())->assertRedirect();
    $this->actingAs($chef)->post(route('chantiers.rapport.store', $chantier), reportPayload(['works' => 'Autre']))
        ->assertSessionHasErrors('works');

    expect(DailyReport::query()->count())->toBe(1);

    Carbon::setTestNow('2026-11-11');
    $this->actingAs($chef)->post(route('chantiers.rapport.store', $chantier), reportPayload())->assertRedirect();
    expect(DailyReport::query()->count())->toBe(2);
});

it('valide le rapport : travaux et effectif obligatoires', function (array $override, string $field) {
    [$chef, $chantier] = fieldSetup();

    $this->actingAs($chef)->post(route('chantiers.rapport.store', $chantier), reportPayload($override))->assertSessionHasErrors($field);
})->with([
    'travaux vides' => [['works' => ''], 'works'],
    'effectif négatif' => [['headcount' => -2], 'headcount'],
    'effectif décimal' => [['headcount' => '3,5'], 'headcount'],
]);

it('liste les rapports sur la fiche avec leur nombre de photos', function () {
    Storage::fake('public');
    Carbon::setTestNow('2026-11-10');
    [$chef, $chantier] = fieldSetup();
    $this->actingAs($chef)->post(route('chantiers.rapport.store', $chantier), reportPayload(['photos' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')]]));

    $this->actingAs($chef)->get(route('chantiers.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.author', $chef->name)
            ->where('reports.0.photos', 2)
            ->where('reports.0.headcount', 34));
});

it('refuse l\'avancement et le rapport à un profil sans droit d\'écriture sur les chantiers', function () {
    $achats = userWithRole(RoleName::ServiceAchatsBtp);
    $chantier = Chantier::factory()->create();
    $task = Task::factory()->create(['chantier_id' => $chantier->id]);

    $this->actingAs($achats)->get(route('chantiers.avancement', $chantier))->assertForbidden();
    $this->actingAs($achats)->post(route('chantiers.avancement.store', $chantier), ['entries' => [['task_id' => $task->id, 'progress' => 50]]])->assertForbidden();
    $this->actingAs($achats)->post(route('chantiers.rapport.store', $chantier), reportPayload())->assertForbidden();

    expect($task->refresh()->progress)->toBe(0);
});

// ------------------------------------------------------------------ alertes calculées côté serveur

it('RG7.5 à RG7.7 : la fiche renvoie l\'état de chaque tâche calculé selon les paramètres', function () {
    $this->seed(SettingsSeeder::class);
    Carbon::setTestNow('2026-11-08');
    $director = userWithRole(RoleName::DirectionTechnique, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $chantier = Chantier::factory()->create();
    // CA7.1 : du 1er au 10, 30 % le 8.
    Task::factory()->create(['chantier_id' => $chantier->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-10', 'progress' => 30, 'last_update_on' => '2026-11-08']);

    $this->actingAs($director)->get(route('chantiers.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page->where('tasks.0.theoretical', 78)->where('tasks.0.health', 'risk'));

    // Le seuil est un paramètre (RG1.3) : à 60 points, la tâche n'est plus à risque.
    Setting::query()->where('key', 'planning.risk_threshold_points')->update(['value' => '60']);
    Cache::forget('erp.settings');

    $this->actingAs($director)->get(route('chantiers.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page->where('tasks.0.health', 'ok'));
});

it('un utilisateur non affecté ne peut pas écrire sur le chantier d\'un collègue', function () {
    [, $chantier, $task] = fieldSetup();
    $stranger = userWithRole(RoleName::ChefChantier);
    User::factory()->create();

    $this->actingAs($stranger)->post(route('chantiers.avancement.store', $chantier), ['entries' => [['task_id' => $task->id, 'progress' => 90]]])->assertForbidden();
    expect($task->refresh()->progress)->toBe(10);
});
