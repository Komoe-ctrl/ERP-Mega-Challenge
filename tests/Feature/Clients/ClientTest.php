<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Activity;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientContact;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// M2 — Clients : RG2.1, RG2.2, CA2.

function clientManager(RoleName $role = RoleName::DirectionTechnique): User
{
    return userWithRole($role, ['two_factor_secret' => encrypt('s'), 'two_factor_recovery_codes' => encrypt(json_encode(['c'])), 'two_factor_confirmed_at' => now()]);
}

function clientPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Immo Ivoire Promotion',
        'type' => 'promoteur',
        'contact' => 'M. Koffi',
        'phone' => '+225 07 00 00 00 00',
        'email' => 'contact@immo-ivoire.ci',
        'city' => 'Abidjan',
        'address' => 'Cocody Riviera 3',
    ];
}

it('RG2.1 : crée un client avec un numéro unique automatique', function () {
    $user = clientManager();

    $this->actingAs($user)->post(route('clients.store'), clientPayload())->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('clients.store'), clientPayload(['name' => 'Autre promoteur', 'contact' => 'Mme Traoré']))->assertSessionHasNoErrors();

    $numbers = Client::query()->orderBy('id')->pluck('number')->all();
    $year = now()->year;
    expect($numbers)->toBe(["CLI-{$year}-0001", "CLI-{$year}-0002"]);
});

it('RG2.1 : signale un doublon sur le nom et le contact, sans tenir compte de la casse ni des espaces', function () {
    $user = clientManager();
    $this->actingAs($user)->post(route('clients.store'), clientPayload())->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('clients.store'), clientPayload(['name' => '  immo ivoire PROMOTION ', 'contact' => 'm. koffi']))
        ->assertSessionHasErrors('name');

    expect(Client::query()->count())->toBe(1);
});

it('RG2.1 : un même nom avec un autre contact n\'est pas un doublon, et un doublon se confirme', function () {
    $user = clientManager();
    $this->actingAs($user)->post(route('clients.store'), clientPayload())->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('clients.store'), clientPayload(['contact' => 'Mme Kouassi']))->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('clients.store'), clientPayload(['confirm_duplicate' => true]))->assertSessionHasNoErrors();

    expect(Client::query()->count())->toBe(3);
});

it('valide la saisie du client (nom, type, téléphone, e-mail)', function (array $override, string $field) {
    $this->actingAs(clientManager())->post(route('clients.store'), clientPayload($override))->assertSessionHasErrors($field);
})->with([
    'nom trop court' => [['name' => 'AB'], 'name'],
    'type inconnu' => [['type' => 'martien'], 'type'],
    'téléphone invalide' => [['phone' => 'abc'], 'phone'],
    'e-mail invalide' => [['email' => 'pas-un-email'], 'email'],
]);

it('met à jour la fiche d\'un client et journalise la modification', function () {
    $user = clientManager();
    $client = Client::factory()->create(['name' => 'Ancien nom']);

    $this->actingAs($user)->put(route('clients.update', $client), clientPayload(['name' => 'Nouveau nom']))->assertSessionHasNoErrors();

    expect($client->refresh()->name)->toBe('Nouveau nom')
        ->and(Activity::query()->where('subject_type', $client->getMorphClass())->where('subject_id', $client->id)->count())->toBeGreaterThan(0);
});

it('RG2.2 / CA2 : la fiche agrège les projets et le nombre de projets en cours', function () {
    $user = clientManager();
    $client = Client::factory()->create();
    Project::factory()->withStatus('en_cours')->create(['client_id' => $client->id]);
    Project::factory()->withStatus('en_cours')->create(['client_id' => $client->id]);
    Project::factory()->withStatus('etude')->create(['client_id' => $client->id]);
    Project::factory()->withStatus('en_cours')->create();

    $this->actingAs($user)->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Show', false)
            ->where('summary.projectsInProgress', 2)
            ->where('summary.projectsTotal', 3)
            ->where('summary.due', 0)
            ->has('projects', 3));
});

it('liste les clients avec recherche et filtre de type', function () {
    $user = clientManager();
    Client::factory()->create(['name' => 'Groupe Atlantique', 'type' => 'entreprise']);
    Client::factory()->create(['name' => 'SIPIM', 'type' => 'administration']);

    $this->actingAs($user)->get(route('clients.index', ['q' => 'atlantique']))
        ->assertInertia(fn (Assert $page) => $page->component('Clients/Index', false)->has('clients.data', 1)->where('clients.data.0.name', 'Groupe Atlantique'));
    $this->actingAs($user)->get(route('clients.index', ['type' => 'administration']))
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.name', 'SIPIM'));
});

it('gère les contacts d\'un client', function () {
    $user = clientManager();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.contacts.store', $client), ['name' => 'M. Diallo', 'role' => 'Directeur', 'email' => 'diallo@example.ci'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('clients.contacts.store', $client), ['name' => ''])->assertSessionHasErrors('name');

    $contact = ClientContact::query()->firstOrFail();
    expect($contact->client_id)->toBe($client->id);

    $this->actingAs($user)->delete(route('clients.contacts.destroy', [$client, $contact]))->assertSessionHasNoErrors();
    expect(ClientContact::query()->count())->toBe(0);
});

it('un contact ne se supprime que par son propre client', function () {
    $user = clientManager();
    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $contact = $other->contacts()->create(['name' => 'Autre']);

    $this->actingAs($user)->delete(route('clients.contacts.destroy', [$client, $contact]))->assertNotFound();

    expect(ClientContact::query()->count())->toBe(1);
});

it('ajoute, télécharge et supprime un document du client', function () {
    Storage::fake('public');
    $user = clientManager();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.documents.store', $client), ['document' => UploadedFile::fake()->create('contrat.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();
    $media = $client->fresh()->getFirstMedia('documents');
    expect($media?->file_name)->toBe('contrat.pdf');

    $this->actingAs($user)->get(route('clients.documents.show', [$client, $media->id]))->assertOk();
    $this->actingAs($user)->delete(route('clients.documents.destroy', [$client, $media->id]))->assertSessionHasNoErrors();
    expect($client->fresh()->getMedia('documents'))->toHaveCount(0);
});

it('refuse un document d\'un type non autorisé', function () {
    Storage::fake('public');
    $client = Client::factory()->create();

    $this->actingAs(clientManager())->post(route('clients.documents.store', $client), ['document' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('document');
});

it('les profils en lecture consultent sans modifier, les autres reçoivent 403', function () {
    $client = Client::factory()->create();

    // Comptabilité : lecture seule des clients.
    $compta = clientManager(RoleName::Comptabilite);
    $this->actingAs($compta)->get(route('clients.show', $client))->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.update', false));
    $this->actingAs($compta)->post(route('clients.store'), clientPayload())->assertForbidden();
    $this->actingAs($compta)->put(route('clients.update', $client), clientPayload())->assertForbidden();
    $this->actingAs($compta)->post(route('clients.contacts.store', $client), ['name' => 'X'])->assertForbidden();

    // Sans droit sur le module : 403 partout, y compris pour l'administrateur.
    $this->actingAs(clientManager(RoleName::Magasinier))->get(route('clients.index'))->assertForbidden();
    $this->actingAs(clientManager(RoleName::Administrateur))->get(route('clients.show', $client))->assertForbidden();
});
