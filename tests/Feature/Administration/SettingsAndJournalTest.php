<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Activity;
use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Administration\Models\Setting;
use App\Domain\Shared\Enums\RoleName;
use Database\Seeders\SettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

// RG1.3 paramètres, RG1.4 exercices clôturés, RG1.5 journal non modifiable.

beforeEach(fn () => $this->seed(SettingsSeeder::class));

/** Valeurs actuelles de tous les paramètres, prêtes à être renvoyées au serveur. */
function settingValues(array $overrides = []): array
{
    return ['values' => $overrides + Setting::query()->pluck('value', 'key')->all()];
}

it('affiche les paramètres par groupe avec leur unité', function () {
    $this->actingAs(adminUser())->get(route('administration.parametres'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Administration/Parametres')
            ->has('settingGroups', 5)
            ->where('settingGroups.0.group', 'Seuils de validation')
            ->where('settingGroups.0.items.0.key', 'validation.direction_threshold_fcfa')
            ->where('settingGroups.0.items.0.unit', 'FCFA')
            ->where('settingGroups.0.items.0.value', '5000000'));
});

it('modifie un seuil sans développement : la nouvelle valeur est lue aussitôt (RG1.3)', function () {
    expect(Setting::get('validation.direction_threshold_fcfa'))->toBe(5_000_000);

    $this->actingAs(adminUser())
        ->put(route('administration.parametres.update'), settingValues(['validation.direction_threshold_fcfa' => '8000000']))
        ->assertRedirect();

    expect(Setting::get('validation.direction_threshold_fcfa'))->toBe(8_000_000);
});

it('refuse des valeurs invalides', function (string $key, string $value, string $message) {
    $this->actingAs(adminUser())
        ->put(route('administration.parametres.update'), settingValues([$key => $value]))
        ->assertSessionHasErrors([('values.'.str_replace('.', '__', $key)) => $message]);

    expect(Setting::query()->where('key', $key)->value('value'))->not->toBe($value);
})->with([
    'pourcentage au-delà de 100' => ['tax.vat_rate', '120', 'La valeur est trop grande.'],
    'montant décimal' => ['validation.direction_threshold_fcfa', '12,5', 'Saisissez un nombre entier.'],
    'moins de trois fournisseurs (RG8.11)' => ['validation.consultation_min_suppliers', '2', 'La valeur est trop petite.'],
    'numérotation sans compteur' => ['numbering.devis', 'DEVIS-SANS-COMPTEUR', 'Le format doit contenir un compteur, ex. {SEQ:5}.'],
]);

it('refuse une alerte critique inférieure à l\'avertissement (RG10.3)', function () {
    $this->actingAs(adminUser())
        ->put(route('administration.parametres.update'), settingValues(['budget.warning_percent' => '90', 'budget.critical_percent' => '70']))
        ->assertSessionHasErrors('values.budget__critical_percent');
});

it('trace chaque modification de paramètre au journal, avec les valeurs avant et après', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('administration.parametres.update'), settingValues(['planning.stale_days' => '10']))
        ->assertRedirect();

    $entry = Activity::query()->where('subject_type', Setting::class)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->attribute_changes?->get('old'))->toMatchArray(['value' => '7'])
        ->and($entry->attribute_changes?->get('attributes'))->toMatchArray(['value' => '10']);
});

it('refuse la modification des paramètres à un profil sans le droit (403)', function () {
    $achats = userWithRole(RoleName::ServiceAchatsBtp);

    $this->actingAs($achats)->get(route('administration.parametres'))->assertForbidden();
    $this->actingAs($achats)->put(route('administration.parametres.update'), settingValues(['tax.vat_rate' => '5']))->assertForbidden();

    expect(Setting::get('tax.vat_rate'))->toBe(18.0);
});

it('clôture un exercice avec un motif : il passe en lecture seule (RG1.4)', function () {
    $admin = adminUser();
    $year = FiscalYear::factory()->create(['label' => '2025']);

    $this->actingAs($admin)->patch(route('administration.fiscal-years.close', $year), [])
        ->assertSessionHasErrors('reason');

    $this->actingAs($admin)->patch(route('administration.fiscal-years.close', $year), ['reason' => 'Bilan approuvé'])
        ->assertRedirect();

    $year->refresh();
    expect($year->isClosed())->toBeTrue()
        ->and($year->closed_by)->toBe($admin->id)
        ->and($admin->can('update', $year))->toBeFalse()
        ->and($admin->can('delete', $year))->toBeFalse();

    $entry = Activity::query()->where('event', 'validation')->latest('id')->firstOrFail();
    expect($entry->getProperty('motif'))->toBe('Bilan approuvé');
});

it('ne clôture pas deux fois le même exercice et refuse un profil sans droit de validation', function () {
    $year = FiscalYear::factory()->create();

    $this->actingAs(userWithRole(RoleName::ChefChantier))
        ->patch(route('administration.fiscal-years.close', $year), ['reason' => 'test'])->assertForbidden();

    $admin = adminUser();
    $this->actingAs($admin)->patch(route('administration.fiscal-years.close', $year), ['reason' => 'Clôture annuelle']);
    $this->actingAs($admin)->patch(route('administration.fiscal-years.close', $year), ['reason' => 'Encore'])->assertForbidden();
});

it('liste le journal paginé, du plus récent au plus ancien, avec valeurs avant et après', function () {
    $admin = adminUser();
    $agency = Agency::factory()->create(['name' => 'Agence Test']);
    $agency->update(['city' => 'Yamoussoukro']);

    $this->actingAs($admin)->get(route('administration.journal'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Administration/Journal')
            ->where('entries.data.0.event', 'modification')
            ->where('entries.data.0.subject', 'Agence « Agence Test »')
            ->where('entries.data.0.changes.0.field', 'city')
            ->where('entries.data.0.changes.0.after', 'Yamoussoukro')
            ->has('actors'));
});

it('filtre le journal par événement', function () {
    $admin = adminUser();
    $agency = Agency::factory()->create();
    $agency->update(['city' => 'Bouaké']);

    $this->actingAs($admin)->get(route('administration.journal', ['event' => 'creation']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data', fn ($rows) => collect($rows)->every(fn ($r) => $r['event'] === 'creation')
                && collect($rows)->isNotEmpty()));
});

it('rend le journal non modifiable : ni mise à jour ni suppression (RG1.5)', function () {
    Agency::factory()->create();
    $activity = Activity::query()->latest('id')->firstOrFail();

    expect(fn () => $activity->update(['description' => 'falsifié']))->toThrow(LogicException::class)
        ->and(fn () => $activity->delete())->toThrow(LogicException::class);

    $modifiable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => $r->uri() === 'administration/journal' && array_diff($r->methods(), ['GET', 'HEAD']) !== []);
    expect($modifiable)->toBeEmpty();
});

it('refuse le journal à un profil sans le droit (403)', function () {
    $this->actingAs(userWithRole(RoleName::Magasinier))->get(route('administration.journal'))->assertForbidden();
});
