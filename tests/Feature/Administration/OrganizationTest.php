<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Site;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

// RG1.1 : agences, services et sites ; jamais de suppression, seulement une
// désactivation motivée (RG1.5 : tracée au journal).

it('liste les agences, services (avec leurs utilisateurs) et sites', function () {
    $admin = adminUser();
    $agency = Agency::factory()->create(['code' => 'BKE', 'name' => 'Agence Bouaké']);
    $service = Service::factory()->create(['code' => 'TRV']);
    Site::factory()->create(['agency_id' => $agency->id, 'type' => 'magasin_chantier']);
    User::factory()->count(2)->create(['service_id' => $service->id]);

    $this->actingAs($admin)->get(route('administration.organisation'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Administration/Organisation')
            ->has('agencies', 1)
            ->where('agencies.0.code', 'BKE')
            ->where('services.0.users', 2)
            ->where('sites.0.agency', 'Agence Bouaké')
            ->where('sites.0.type', 'magasin_chantier'));
});

it('crée une agence, un service et un site (code mis en majuscules)', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('administration.agencies.store'), [
        'code' => 'bke', 'name' => 'Agence Bouaké', 'city' => 'Bouaké',
    ])->assertRedirect();
    $this->actingAs($admin)->post(route('administration.services.store'), [
        'code' => 'qhse', 'name' => 'Qualité et sécurité',
    ])->assertRedirect();

    $agency = Agency::query()->where('code', 'BKE')->firstOrFail();
    $this->actingAs($admin)->post(route('administration.sites.store'), [
        'code' => 'bke-depot', 'name' => 'Dépôt Bouaké', 'agency_id' => $agency->id, 'type' => 'depot_central',
    ])->assertRedirect();

    expect(Service::query()->where('code', 'QHSE')->exists())->toBeTrue()
        ->and(Site::query()->where('code', 'BKE-DEPOT')->value('agency_id'))->toBe($agency->id);
});

it('refuse un code déjà utilisé ou mal formé', function () {
    $admin = adminUser();
    Agency::factory()->create(['code' => 'ABJ']);

    $this->actingAs($admin)->post(route('administration.agencies.store'), [
        'code' => 'abj', 'name' => 'Doublon', 'city' => 'Abidjan',
    ])->assertSessionHasErrors(['code' => 'Ce code existe déjà.']);

    $this->actingAs($admin)->post(route('administration.agencies.store'), [
        'code' => 'A1', 'name' => 'Mauvais code', 'city' => 'Abidjan',
    ])->assertSessionHasErrors(['code' => 'Le code compte 2 à 6 lettres.']);
});

it('exige une agence existante pour un site', function () {
    $this->actingAs(adminUser())->post(route('administration.sites.store'), [
        'code' => 'SITE-1', 'name' => 'Site sans agence', 'agency_id' => 9999, 'type' => 'bureau',
    ])->assertSessionHasErrors('agency_id');
});

it('désactive avec un motif obligatoire, trace le motif et réactive', function () {
    $admin = adminUser();
    $agency = Agency::factory()->create();

    $this->actingAs($admin)->patch(route('administration.agencies.deactivate', $agency), [])
        ->assertSessionHasErrors(['reason' => 'Le motif est obligatoire.']);
    expect($agency->fresh()?->is_active)->toBeTrue();

    $this->actingAs($admin)->patch(route('administration.agencies.deactivate', $agency), ['reason' => 'Fermeture de l\'agence'])
        ->assertRedirect();
    expect($agency->fresh()?->is_active)->toBeFalse();

    $entry = Activity::query()->where('event', 'deactivation')->latest('id')->firstOrFail();
    expect($entry->getProperty('motif'))->toBe('Fermeture de l\'agence')
        ->and($entry->causer_id)->toBe($admin->id);

    $this->actingAs($admin)->patch(route('administration.agencies.reactivate', $agency))->assertRedirect();
    expect($agency->fresh()?->is_active)->toBeTrue();
});

it('ne permet jamais de supprimer une agence, un service ou un site', function () {
    $deletable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true) && preg_match('#^administration/(agencies|services|sites)#', $r->uri()) === 1);

    expect($deletable)->toBeEmpty();
});

it('refuse l\'organisation à un profil sans le droit (403), lecture comme écriture', function () {
    $chef = userWithRole(RoleName::ChefChantier);
    $agency = Agency::factory()->create();

    $this->actingAs($chef)->get(route('administration.organisation'))->assertForbidden();
    $this->actingAs($chef)->post(route('administration.agencies.store'), [
        'code' => 'XYZ', 'name' => 'Pirate', 'city' => 'Abidjan',
    ])->assertForbidden();
    $this->actingAs($chef)->patch(route('administration.agencies.deactivate', $agency), ['reason' => 'test'])->assertForbidden();

    expect($agency->fresh()?->is_active)->toBeTrue()
        ->and(Agency::query()->where('code', 'XYZ')->exists())->toBeFalse();
});
