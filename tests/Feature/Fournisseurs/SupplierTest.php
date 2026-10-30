<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function buyer(): User
{
    return userWithRole(RoleName::ServiceAchatsBtp);
}

function supplierPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Sotraci Matériaux',
        'contact' => 'Moussa Diomandé',
        'phone' => '+225 27 21 35 11 20',
        'email' => 'contact@sotraci.example',
        'city' => 'Abidjan, Yopougon',
        'categories' => ['Matériaux'],
    ];
}

it('crée un fournisseur avec son numéro FRS-NNNN et ses catégories (RG3.1)', function () {
    $this->actingAs(buyer())->post(route('fournisseurs.store'), supplierPayload(['categories' => ['Matériaux', 'Prestations techniques']]))
        ->assertRedirect();
    $this->actingAs(buyer())->post(route('fournisseurs.store'), supplierPayload(['name' => 'Location Engins Atlantique', 'categories' => ['Engins']]));

    $first = Supplier::query()->orderBy('id')->firstOrFail();
    expect($first->number)->toBe('FRS-0001')
        ->and($first->categoryNames())->toBe(['Matériaux', 'Prestations techniques'])
        ->and(Supplier::query()->orderByDesc('id')->value('number'))->toBe('FRS-0002');
});

it('valide la fiche : raison sociale unique, téléphone, catégories connues', function (array $override, string $field) {
    Supplier::factory()->create(['name' => 'Doublon SA']);

    $this->actingAs(buyer())->post(route('fournisseurs.store'), supplierPayload($override))->assertSessionHasErrors($field);
})->with([
    'doublon (sans tenir compte des espaces)' => [['name' => '  Doublon SA '], 'name'],
    'nom trop court' => [['name' => 'ab'], 'name'],
    'téléphone invalide' => [['phone' => 'abc'], 'phone'],
    'e-mail invalide' => [['email' => 'pas-un-email'], 'email'],
    'aucune catégorie' => [['categories' => []], 'categories'],
    'catégorie inconnue' => [['categories' => ['Bijoux']], 'categories.0'],
    'ville manquante' => [['city' => ''], 'city'],
]);

it('RG3.1 : modifie les catégories d\'un fournisseur', function () {
    $supplier = Supplier::factory()->inCategories('Matériaux', 'Transport')->create();

    $this->actingAs(buyer())->put(route('fournisseurs.update', $supplier), supplierPayload(['name' => $supplier->name, 'categories' => ['Transport', 'Engins']]))
        ->assertRedirect();

    expect($supplier->refresh()->load('categories')->categoryNames())->toBe(['Transport', 'Engins']);
});

it('RG3.1 : la liste se filtre par catégorie, état et recherche', function () {
    Supplier::factory()->inCategories('Matériaux')->create(['name' => 'Ciments du Golfe', 'city' => 'Vridi']);
    Supplier::factory()->inCategories('Engins')->create(['name' => 'Engins Atlantique']);
    Supplier::factory()->inCategories('Matériaux')->create(['name' => 'Ancien fournisseur', 'is_active' => false]);
    Supplier::factory()->count(15)->inCategories('Transport')->create();

    $user = buyer();
    $this->actingAs($user)->get(route('fournisseurs.index'))
        ->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 15)->where('suppliers.total', 18));
    $this->actingAs($user)->get(route('fournisseurs.index', ['category' => 'Engins']))
        ->assertInertia(fn (Assert $page) => $page->where('suppliers.total', 1)->where('suppliers.data.0.name', 'Engins Atlantique'));
    $this->actingAs($user)->get(route('fournisseurs.index', ['state' => 'inactif']))
        ->assertInertia(fn (Assert $page) => $page->where('suppliers.total', 1)->where('suppliers.data.0.active', false));
    $this->actingAs($user)->get(route('fournisseurs.index', ['q' => 'Vridi']))
        ->assertInertia(fn (Assert $page) => $page->where('suppliers.total', 1));
});

it('désactive un fournisseur avec un motif tracé, le réactive, et ne le supprime jamais', function () {
    $user = buyer();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)->patch(route('fournisseurs.deactivate', $supplier), [])->assertSessionHasErrors('reason');
    $this->actingAs($user)->patch(route('fournisseurs.deactivate', $supplier), ['reason' => 'Retards répétés'])->assertRedirect();
    expect($supplier->refresh()->is_active)->toBeFalse()
        ->and(Activity::query()->where('event', 'deactivation')->latest('id')->first()?->getProperty('motif'))->toBe('Retards répétés');

    $this->actingAs($user)->patch(route('fournisseurs.reactivate', $supplier))->assertRedirect();
    expect($supplier->refresh()->is_active)->toBeTrue();

    $deletable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true) && str_starts_with($r->uri(), 'fournisseurs'));
    expect($deletable)->toBeEmpty()->and($user->can('delete', $supplier))->toBeFalse();
});

it('RG3.2 : enregistre un tarif par article et met à jour le tarif existant sans le dupliquer', function () {
    $user = buyer();
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create(['price' => 6_500]);

    $this->actingAs($user)->post(route('fournisseurs.tariffs.store', $supplier), ['article_id' => $article->id, 'price' => 6_300])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('fournisseurs.tariffs.store', $supplier), ['article_id' => $article->id, 'price' => 6_100])->assertSessionHasNoErrors();

    expect(SupplierTariff::query()->count())->toBe(1)->and(SupplierTariff::query()->value('price'))->toBe(6_100);

    $this->actingAs($user)->get(route('fournisseurs.show', $supplier))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fournisseurs/Show')
            ->where('tariffs.0.price', 6_100)
            ->where('tariffs.0.referencePrice', 6_500)
            ->where('canUpdate', true));
});

it('RG3.2 : refuse un tarif invalide ou sur un article désactivé', function (array $override, string $field) {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    $inactive = Article::factory()->create(['is_active' => false]);
    $payload = $override + ['article_id' => $article->id, 'price' => 1_000];
    if (($override['article_id'] ?? null) === 'inactive') {
        $payload['article_id'] = $inactive->id;
    }

    $this->actingAs(buyer())->post(route('fournisseurs.tariffs.store', $supplier), $payload)->assertSessionHasErrors($field);
    expect(SupplierTariff::query()->count())->toBe(0);
})->with([
    'prix décimal' => [['price' => 10.5], 'price'],
    'prix nul' => [['price' => 0], 'price'],
    'article désactivé' => [['article_id' => 'inactive'], 'article_id'],
    'article inconnu' => [['article_id' => 99999], 'article_id'],
]);

it('autorise la lecture aux profils concernés et réserve l\'écriture au Service Achats BTP (403 sinon)', function (RoleName $role) {
    $supplier = Supplier::factory()->create();
    $user = userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->get(route('fournisseurs.index'))->assertOk();
    $this->actingAs($user)->get(route('fournisseurs.show', $supplier))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUpdate', false));
    $this->actingAs($user)->post(route('fournisseurs.store'), supplierPayload())->assertForbidden();
    $this->actingAs($user)->put(route('fournisseurs.update', $supplier), supplierPayload(['name' => 'Autre nom']))->assertForbidden();
    $this->actingAs($user)->patch(route('fournisseurs.deactivate', $supplier), ['reason' => 'test'])->assertForbidden();
    $this->actingAs($user)->post(route('fournisseurs.tariffs.store', $supplier), ['article_id' => Article::factory()->create()->id, 'price' => 100])->assertForbidden();

    expect($supplier->refresh()->is_active)->toBeTrue()->and(Supplier::query()->count())->toBe(1);
})->with([RoleName::Finance, RoleName::DirectionGenerale]);

it('refuse les fournisseurs aux profils sans accès au module (403)', function (RoleName $role) {
    $user = userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->actingAs($user)->get(route('fournisseurs.index'))->assertForbidden();
})->with([RoleName::ChefChantier, RoleName::ConducteurTravaux, RoleName::Magasinier, RoleName::RessourcesHumaines, RoleName::DirectionTechnique]);

it('trace la création et la modification au journal des activités', function () {
    $user = buyer();
    $this->actingAs($user)->post(route('fournisseurs.store'), supplierPayload());
    $supplier = Supplier::query()->firstOrFail();
    $this->actingAs($user)->put(route('fournisseurs.update', $supplier), supplierPayload(['city' => 'Bouaké']));

    $update = Activity::query()->where('subject_type', Supplier::class)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($update->causer_id)->toBe($user->id)
        ->and($update->attribute_changes?->get('old'))->toMatchArray(['city' => 'Abidjan, Yopougon'])
        ->and($update->attribute_changes?->get('attributes'))->toMatchArray(['city' => 'Bouaké']);
});
