<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Administration\Models\Activity;
use App\Domain\Clients\Models\Client;
use App\Domain\Devis\Models\Ouvrage;
use App\Domain\Devis\Models\Quote;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// M5 — Devis : RG5.1, RG5.2, RG5.3, CA5.

function quoteUser(RoleName $role): User
{
    return userWithRole($role, ['two_factor_secret' => encrypt('s'), 'two_factor_recovery_codes' => encrypt(json_encode(['c'])), 'two_factor_confirmed_at' => now()]);
}

function quoteProject(string $status = 'etude'): Project
{
    return Project::factory()->withStatus($status)->create();
}

function draftQuote(User $author, ?Project $project = null): Quote
{
    $project ??= quoteProject();

    test()->actingAs($author)->post(route('devis.store'), [
        'client_id' => $project->client_id, 'project_id' => $project->id, 'vat_rate' => 18, 'discount_rate' => 0,
    ])->assertSessionHasNoErrors();

    return Quote::query()->latest('id')->firstOrFail();
}

function saveLines(Quote $quote, User $by, ?array $works = null, array $overrides = []): void
{
    test()->actingAs($by)->put(route('devis.update', $quote), $overrides + [
        'discount_rate' => 0, 'vat_rate' => 18, 'direct_cost' => 600_000,
        'works' => $works ?? [['label' => 'Maçonnerie de parpaings', 'unit' => 'm²', 'quantity' => 100, 'unit_price' => 10_000]],
    ])->assertSessionHasNoErrors();
}

it('crée un devis en brouillon avec un numéro unique', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $first = draftQuote($dt);
    $second = draftQuote($dt);

    $year = now()->year;
    expect($first->number)->toBe("DEV-{$year}-00001")
        ->and($second->number)->toBe("DEV-{$year}-00002")
        ->and($first->version)->toBe(1)
        ->and($first->status->getValue())->toBe('brouillon');
});

it('refuse un projet qui n\'appartient pas au client ou qui n\'est plus au stade devis', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $project = quoteProject();

    $this->actingAs($dt)->post(route('devis.store'), ['client_id' => Client::factory()->create()->id, 'project_id' => $project->id, 'vat_rate' => 18])
        ->assertSessionHasErrors('project_id');

    $running = quoteProject('en_cours');
    $this->actingAs($dt)->post(route('devis.store'), ['client_id' => $running->client_id, 'project_id' => $running->id, 'vat_rate' => 18])
        ->assertSessionHasErrors('project_id');

    expect(Quote::query()->count())->toBe(0);
});

it('RG5.2 : calcule les totaux et la marge prévue = total HT − déboursé sec', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);
    saveLines($quote, $dt, [
        ['label' => 'Maçonnerie de parpaings', 'unit' => 'm²', 'quantity' => 100, 'unit_price' => 10_000],
        ['label' => 'Peinture intérieure', 'unit' => 'm²', 'quantity' => 50.5, 'unit_price' => 2_000],
    ], ['discount_rate' => 10, 'direct_cost' => 800_000]);

    $totals = $quote->fresh()->load('works')->totals();

    // 1 000 000 + 101 000 = 1 101 000 ; remise 10 % = 110 100 ; HT 990 900 ; TVA 18 % = 178 362.
    expect($totals['subtotal'])->toBe(1_101_000)
        ->and($totals['discount'])->toBe(110_100)
        ->and($totals['ht'])->toBe(990_900)
        ->and($totals['vat'])->toBe(178_362)
        ->and($totals['ttc'])->toBe(1_169_262)
        ->and($totals['margin'])->toBe(190_900)
        ->and($totals['marginRate'])->toBe(19.27);
});

it('valide les lignes : libellé, quantité positive, prix et remise bornée', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);

    $this->actingAs($dt)->put(route('devis.update', $quote), [
        'discount_rate' => 120, 'vat_rate' => 18, 'direct_cost' => 0,
        'works' => [['label' => 'ab', 'unit' => 'm²', 'quantity' => 0, 'unit_price' => -5]],
    ])->assertSessionHasErrors(['discount_rate', 'works.0.label', 'works.0.quantity', 'works.0.unit_price']);
});

it('RG5.1 : un ouvrage de la bibliothèque a son prix calculé depuis sa composition', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $sable = Article::factory()->create(['price' => 8_500]);
    $ouvrage = Ouvrage::query()->create(['label' => 'Remblai sableux', 'unit' => 'm³', 'is_active' => true]);
    $ouvrage->components()->create(['article_id' => $sable->id, 'quantity' => 1.2]); // 10 200
    $ouvrage->components()->create(['label' => 'Main-d\'œuvre', 'quantity' => 1, 'unit_price' => 2_000]); // 2 000

    // Déboursé 12 200 × 1,25 = 15 250, arrondi à la centaine : 15 300.
    expect($ouvrage->fresh()->load('components.article')->cost())->toBe(12_200)
        ->and($ouvrage->price())->toBe(15_300);

    $quote = draftQuote($dt);
    $this->actingAs($dt)->post(route('devis.ouvrages.store', $quote), ['ouvrage_id' => $ouvrage->id, 'quantity' => 10])
        ->assertSessionHasNoErrors();

    $quote->refresh()->load('works');
    expect($quote->works)->toHaveCount(1)
        ->and($quote->works[0]->unit_price)->toBe(15_300)
        ->and($quote->works[0]->components)->toContain('Main-d\'œuvre')
        ->and($quote->direct_cost)->toBe(122_000);
});

it('refuse d\'ajouter un ouvrage avec une quantité nulle', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $ouvrage = Ouvrage::query()->create(['label' => 'Ouvrage test', 'unit' => 'u', 'is_active' => true]);
    $quote = draftQuote($dt);

    $this->actingAs($dt)->post(route('devis.ouvrages.store', $quote), ['ouvrage_id' => $ouvrage->id, 'quantity' => 0])
        ->assertSessionHasErrors('quantity');
});

it('refuse de soumettre un devis sans ouvrage', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);

    $this->actingAs($dt)->post(route('devis.submit', $quote))->assertSessionHasErrors('works');
    expect($quote->fresh()->status->getValue())->toBe('brouillon');
});

it('suit le cycle brouillon → soumis → validé et fait passer le projet de étude à devis', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $dg = quoteUser(RoleName::DirectionGenerale);
    $project = quoteProject('etude');
    $quote = draftQuote($dt, $project);
    saveLines($quote, $dt);

    $this->actingAs($dt)->post(route('devis.submit', $quote))->assertSessionHasNoErrors();
    expect($quote->fresh()->status->getValue())->toBe('soumis');

    // Soumis : plus modifiable.
    $this->actingAs($dt)->put(route('devis.update', $quote), ['discount_rate' => 0, 'vat_rate' => 18, 'direct_cost' => 0, 'works' => []])->assertForbidden();

    $this->actingAs($dg)->post(route('devis.validate', $quote))->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status->getValue())->toBe('valide')
        ->and($quote->decided_by)->toBe($dg->id)
        ->and($project->fresh()->status->getValue())->toBe('devis');
});

it('séparation des tâches : l\'auteur ne valide ni ne refuse son propre devis', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);
    saveLines($quote, $dt);
    $this->actingAs($dt)->post(route('devis.submit', $quote));

    $this->actingAs($dt)->post(route('devis.validate', $quote))->assertForbidden();
    $this->actingAs($dt)->post(route('devis.refuse', $quote), ['reason' => 'Test'])->assertForbidden();
    expect($quote->fresh()->status->getValue())->toBe('soumis');
});

it('RG5.3 : un devis refusé se duplique en nouvelle version, l\'ancienne est conservée', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $dg = quoteUser(RoleName::DirectionGenerale);
    $quote = draftQuote($dt);
    saveLines($quote, $dt);
    $this->actingAs($dt)->post(route('devis.submit', $quote));

    $this->actingAs($dg)->post(route('devis.refuse', $quote), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($dg)->post(route('devis.refuse', $quote), ['reason' => 'Marge trop faible'])->assertSessionHasNoErrors();
    expect($quote->fresh()->refusal_reason)->toBe('Marge trop faible');

    $this->actingAs($dt)->post(route('devis.version', $quote))->assertSessionHasNoErrors();

    $v2 = Quote::query()->where('number', $quote->number)->where('version', 2)->firstOrFail();
    expect($v2->status->getValue())->toBe('brouillon')
        ->and($v2->works)->toHaveCount(1)
        ->and($v2->direct_cost)->toBe(600_000)
        ->and($quote->fresh()->status->getValue())->toBe('refuse')
        ->and(Quote::query()->where('number', $quote->number)->count())->toBe(2);

    // Une seule nouvelle version par refus.
    $this->actingAs($dt)->post(route('devis.version', $quote))->assertSessionHasErrors('status');
});

it('refuse de dupliquer un devis qui n\'est pas refusé', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);

    $this->actingAs($dt)->post(route('devis.version', $quote))->assertSessionHasErrors('status');
});

it('filtre la liste par statut et par recherche, avec le total HT', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $a = draftQuote($dt);
    saveLines($a, $dt);
    draftQuote($dt);
    $this->actingAs($dt)->post(route('devis.submit', $a));

    $this->actingAs($dt)->get(route('devis.index', ['status' => 'soumis']))
        ->assertInertia(fn (Assert $page) => $page->component('Devis/Index')->has('quotes.data', 1)
            ->where('quotes.data.0.ht', 1_000_000)->where('quotes.data.0.status', 'soumis'));

    $this->actingAs($dt)->get(route('devis.index', ['q' => $a->number]))
        ->assertInertia(fn (Assert $page) => $page->has('quotes.data', 1));
});

it('affiche le devis avec ses lignes, ses versions et la bibliothèque', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    Ouvrage::query()->create(['label' => 'Ouvrage test', 'unit' => 'u', 'is_active' => true]);
    $quote = draftQuote($dt);
    saveLines($quote, $dt);

    $this->actingAs($dt)->get(route('devis.show', $quote))
        ->assertInertia(fn (Assert $page) => $page->component('Devis/Show')
            ->where('quote.works.0.label', 'Maçonnerie de parpaings')
            ->where('quote.ht', 1_000_000)
            ->has('versions', 1)->has('library', 1)
            ->where('can.update', true)->where('can.submit', true));
});

it('journalise les changements de statut du devis', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);
    saveLines($quote, $dt);
    $this->actingAs($dt)->post(route('devis.submit', $quote));

    expect(Activity::query()->where('log_name', 'devis')->where('subject_id', $quote->id)->count())->toBeGreaterThanOrEqual(2);
});

it('refuse les écrans de devis à un profil sans droit et l\'écriture à un profil en lecture', function () {
    $dt = quoteUser(RoleName::DirectionTechnique);
    $quote = draftQuote($dt);

    $this->actingAs(quoteUser(RoleName::Magasinier))->get(route('devis.index'))->assertForbidden();
    $this->actingAs(quoteUser(RoleName::Administrateur))->get(route('devis.show', $quote))->assertForbidden();

    $dg = quoteUser(RoleName::DirectionGenerale);
    $this->actingAs($dg)->get(route('devis.show', $quote))->assertOk();
    $this->actingAs($dg)->put(route('devis.update', $quote), ['discount_rate' => 0, 'vat_rate' => 18, 'direct_cost' => 0, 'works' => []])->assertForbidden();
    $project = quoteProject();
    $this->actingAs($dg)->post(route('devis.store'), ['client_id' => $project->client_id, 'project_id' => $project->id, 'vat_rate' => 18])->assertForbidden();
});
