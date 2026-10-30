<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\MissingArticleReport;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

function purchasing()
{
    return userWithRole(RoleName::ServiceAchatsBtp);
}

function articlePayload(array $overrides = []): array
{
    return $overrides + [
        'reference' => 'CIM-003',
        'label' => 'Ciment CPJ 32,5 — sac 50 kg',
        'category' => 'Ciment',
        'unit' => 'sac',
        'price' => 6_200,
    ];
}

function articleWorkbook(array $rows): UploadedFile
{
    $export = new class($rows) implements FromArray
    {
        public function __construct(private array $rows) {}

        public function array(): array
        {
            return $this->rows;
        }
    };

    return UploadedFile::fake()->createWithContent('referentiel.xlsx', Excel::raw($export, ExcelType::XLSX));
}

// ------------------------------------------------------------------ RG8.12 : fiche article

it('RG8.12 : crée un article avec son prix et l\'historise dès la création', function () {
    $user = purchasing();

    $this->actingAs($user)->post(route('achats.referentiel.store'), articlePayload(['reference' => 'cim-003']))
        ->assertRedirect(route('achats.referentiel'));

    $article = Article::query()->where('reference', 'CIM-003')->firstOrFail();
    expect($article->price)->toBe(6_200)->and($article->is_active)->toBeTrue();
    $history = $article->priceHistory()->first();
    expect($history->price)->toBe(6_200)->and($history->user_id)->toBe($user->id)->and($history->reason)->toBe('Création de l\'article');
});

it('RG8.12 : valide la référence, la catégorie, l\'unité et le prix entier en FCFA', function (array $override, string $field) {
    Article::factory()->create(['reference' => 'FER-008']);

    $this->actingAs(purchasing())->post(route('achats.referentiel.store'), articlePayload($override))->assertSessionHasErrors($field);
})->with([
    'référence mal formée' => [['reference' => 'CIMENT1'], 'reference'],
    'référence déjà utilisée' => [['reference' => 'FER-008'], 'reference'],
    'catégorie inconnue' => [['category' => 'Bijoux'], 'category'],
    'unité inconnue' => [['unit' => 'carat'], 'unit'],
    'prix décimal' => [['price' => '6200.5'], 'price'],
    'prix nul' => [['price' => 0], 'price'],
    'libellé trop court' => [['label' => 'ab'], 'label'],
]);

// ------------------------------------------------------------------ RG8.13 : historique des prix

it('RG8.13 : un changement de prix exige un motif et est historisé avec son auteur', function () {
    $user = purchasing();
    $article = Article::factory()->create(['reference' => 'CIM-001', 'price' => 6_500]);

    $this->actingAs($user)->put(route('achats.referentiel.update', $article), articlePayload(['reference' => 'CIM-001', 'price' => 6_900]))
        ->assertSessionHasErrors(['reason' => 'Indiquez le motif du changement de prix.']);
    expect($article->refresh()->price)->toBe(6_500);

    $this->actingAs($user)->put(route('achats.referentiel.update', $article), articlePayload(['reference' => 'CIM-001', 'price' => 6_900, 'reason' => 'Hausse du transport']))
        ->assertRedirect();

    $article->refresh();
    $last = $article->priceHistory()->get()->last();
    expect($article->price)->toBe(6_900)
        ->and($last->price)->toBe(6_900)
        ->and($last->reason)->toBe('Hausse du transport')
        ->and($last->user_id)->toBe($user->id);
});

it('RG8.13 : modifier le libellé sans changer le prix n\'ajoute pas d\'historique', function () {
    $article = Article::factory()->create(['reference' => 'CIM-001', 'price' => 6_500]);

    $this->actingAs(purchasing())->put(route('achats.referentiel.update', $article), articlePayload(['reference' => 'CIM-001', 'price' => 6_500, 'label' => 'Ciment renommé']))
        ->assertRedirect();

    expect($article->refresh()->label)->toBe('Ciment renommé')->and($article->priceHistory()->count())->toBe(0);
});

it('affiche la fiche d\'un article avec l\'historique de ses prix', function () {
    $user = purchasing();
    $article = Article::factory()->create(['price' => 6_500]);
    $article->priceHistory()->create(['price' => 6_000, 'user_id' => $user->id, 'reason' => 'Création de l\'article']);
    $article->priceHistory()->create(['price' => 6_500, 'user_id' => $user->id, 'reason' => 'Mise à jour annuelle']);

    $this->actingAs($user)->get(route('achats.referentiel.edit', $article))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achats/Referentiel/Form')
            ->where('canWrite', true)
            ->has('article.history', 2)
            ->where('article.history.1.reason', 'Mise à jour annuelle')
            ->where('article.history.1.by', $user->name));
});

// ------------------------------------------------------------------ RG8.14 : désactivation

it('RG8.14 : désactive un article avec un motif tracé, le réactive, et ne le supprime jamais', function () {
    $user = purchasing();
    $article = Article::factory()->create();

    $this->actingAs($user)->patch(route('achats.referentiel.deactivate', $article), [])->assertSessionHasErrors('reason');
    expect($article->refresh()->is_active)->toBeTrue();

    $this->actingAs($user)->patch(route('achats.referentiel.deactivate', $article), ['reason' => 'Article remplacé'])->assertRedirect();
    expect($article->refresh()->is_active)->toBeFalse();
    expect(Activity::query()->where('event', 'deactivation')->latest('id')->first()?->getProperty('motif'))->toBe('Article remplacé');

    $this->actingAs($user)->patch(route('achats.referentiel.reactivate', $article))->assertRedirect();
    expect($article->refresh()->is_active)->toBeTrue();

    $deletable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true) && str_contains($r->uri(), 'referentiel'));
    expect($deletable)->toBeEmpty()->and($user->can('delete', $article))->toBeFalse();
});

// ------------------------------------------------------------------ CA8.3 : droits

it('CA8.3 : un utilisateur hors Service Achats ne peut modifier ni créer un prix, même en forgeant la requête', function (RoleName $role) {
    $user = userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $article = Article::factory()->create(['reference' => 'CIM-001', 'price' => 6_500]);

    $this->actingAs($user)->post(route('achats.referentiel.store'), articlePayload())->assertForbidden();
    $this->actingAs($user)->put(route('achats.referentiel.update', $article), articlePayload(['reference' => 'CIM-001', 'price' => 1, 'reason' => 'pirate']))->assertForbidden();
    $this->actingAs($user)->patch(route('achats.referentiel.deactivate', $article), ['reason' => 'pirate'])->assertForbidden();
    $this->actingAs($user)->post(route('achats.referentiel.import'), ['file' => articleWorkbook([['Réf', 'Libellé', 'Cat', 'Unité', 'Prix'], ['AAA-001', 'Pirate', 'Fer', 'kg', 5]])])->assertForbidden();

    expect($article->refresh()->price)->toBe(6_500)
        ->and($article->is_active)->toBeTrue()
        ->and(Article::query()->count())->toBe(1);
})->with([
    'conducteur de travaux' => RoleName::ConducteurTravaux,
    'chef de chantier' => RoleName::ChefChantier,
    'magasinier' => RoleName::Magasinier,
    'direction technique' => RoleName::DirectionTechnique,
    'administrateur' => RoleName::Administrateur,
]);

it('laisse les profils Achats consulter le référentiel en lecture seule', function () {
    $conducteur = userWithRole(RoleName::ConducteurTravaux);
    Article::factory()->count(3)->create();

    $this->actingAs($conducteur)->get(route('achats.referentiel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canWrite', false)->where('articles.total', 3)->where('missingReports', []));
});

it('refuse le référentiel aux profils sans accès au module Achats (403)', function () {
    $this->actingAs(userWithRole(RoleName::RessourcesHumaines))->get(route('achats.referentiel'))->assertForbidden();
});

it('liste les articles paginés et filtrés par catégorie, état et recherche', function () {
    Article::factory()->count(20)->create(['category' => 'Fer']);
    Article::factory()->create(['category' => 'Bois', 'label' => 'Chevron rare', 'is_active' => false]);

    $this->actingAs(purchasing())->get(route('achats.referentiel'))
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 15)->where('articles.total', 21));
    $this->actingAs(purchasing())->get(route('achats.referentiel', ['category' => 'Bois']))
        ->assertInertia(fn (Assert $page) => $page->where('articles.total', 1));
    $this->actingAs(purchasing())->get(route('achats.referentiel', ['state' => 'inactif']))
        ->assertInertia(fn (Assert $page) => $page->where('articles.total', 1)->where('articles.data.0.active', false));
    $this->actingAs(purchasing())->get(route('achats.referentiel', ['q' => 'rare']))
        ->assertInertia(fn (Assert $page) => $page->where('articles.total', 1));
});

// ------------------------------------------------------------------ RG8.16 : import

it('RG8.16 : charge le référentiel par import Excel et historise chaque prix', function () {
    $user = purchasing();
    $file = articleWorkbook([
        ['Référence', 'Libellé', 'Catégorie', 'Unité', 'Prix unitaire'],
        ['CIM-001', 'Ciment CPJ 42,5 — sac 50 kg', 'Ciment', 'sac', 6500],
        ['fer-012', 'Fer HA 12 — barre 12 m', 'fer', 'BARRE', '6 600'],
        ['SAB-001', 'Sable de lagune', 'Sable', 'm³', 8500.0],
    ]);

    $this->actingAs($user)->post(route('achats.referentiel.import'), ['file' => $file])->assertSessionHasNoErrors();

    expect(Article::query()->count())->toBe(3);
    $fer = Article::query()->where('reference', 'FER-012')->firstOrFail();
    expect($fer->category)->toBe('Fer')->and($fer->unit)->toBe('barre')->and($fer->price)->toBe(6_600)
        ->and($fer->priceHistory()->first()->reason)->toBe('Import initial du référentiel');
});

it('RG8.16 : charge aussi un CSV avec séparateur point-virgule et accents Windows', function () {
    $csv = mb_convert_encoding("Référence;Libellé;Catégorie;Unité;Prix\nCIM-001;Ciment CPJ 42,5;Ciment;sac;6500\nELE-001;Câble 2,5 mm²;Électricité;couronne;38000\n", 'Windows-1252', 'UTF-8');

    $this->actingAs(purchasing())->post(route('achats.referentiel.import'), ['file' => UploadedFile::fake()->createWithContent('referentiel.csv', $csv)])
        ->assertSessionHasNoErrors();

    expect(Article::query()->where('reference', 'ELE-001')->value('label'))->toBe('Câble 2,5 mm²');
});

it('un import met à jour une référence existante en historisant son nouveau prix', function () {
    $user = purchasing();
    Article::factory()->create(['reference' => 'CIM-001', 'label' => 'Ciment CPJ 42,5', 'category' => 'Ciment', 'unit' => 'sac', 'price' => 6_000]);

    $this->actingAs($user)->post(route('achats.referentiel.import'), ['file' => articleWorkbook([
        ['Référence', 'Libellé', 'Catégorie', 'Unité', 'Prix'],
        ['CIM-001', 'Ciment CPJ 42,5', 'Ciment', 'sac', 6_500],
    ])])->assertSessionHasNoErrors();

    $article = Article::query()->where('reference', 'CIM-001')->firstOrFail();
    expect($article->price)->toBe(6_500)->and($article->priceHistory()->first()->reason)->toBe('Import du référentiel');
});

it('RG8.16 : refuse tout l\'import si une ligne est invalide, en indiquant la ligne', function () {
    $file = articleWorkbook([
        ['Référence', 'Libellé', 'Catégorie', 'Unité', 'Prix'],
        ['CIM-001', 'Ciment CPJ 42,5', 'Ciment', 'sac', 6500],
        ['FER-0', 'Fer', 'Quincaille', 'carat', '12,5'],
        ['CIM-001', 'Doublon', 'Ciment', 'sac', 100],
    ]);

    $this->actingAs(purchasing())->post(route('achats.referentiel.import'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Article::query()->count())->toBe(0);
});

it('refuse un import vide ou qui n\'est pas un fichier tableur', function () {
    $this->actingAs(purchasing())->post(route('achats.referentiel.import'), ['file' => articleWorkbook([['Référence', 'Libellé', 'Catégorie', 'Unité', 'Prix']])])
        ->assertSessionHasErrors('file');
    $this->actingAs(purchasing())->post(route('achats.referentiel.import'), ['file' => UploadedFile::fake()->create('referentiel.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
});

// ------------------------------------------------------------------ RG8.15 : article manquant

it('RG8.15 : tout demandeur signale un article manquant, que seul le Service Achats reçoit', function () {
    $chef = userWithRole(RoleName::ChefChantier);
    $chantier = Chantier::factory()->create();

    $this->actingAs($chef)->post(route('achats.referentiel.signalements.store'), ['label' => 'Treillis soudé ST25', 'chantier_id' => $chantier->id])
        ->assertRedirect();

    $report = MissingArticleReport::query()->firstOrFail();
    expect($report->requester_id)->toBe($chef->id)->and($report->chantier_id)->toBe($chantier->id)->and($report->status)->toBe('a_traiter');

    // Le demandeur ne voit pas la liste ; le Service Achats la voit.
    $this->actingAs($chef)->get(route('achats.referentiel'))->assertInertia(fn (Assert $page) => $page->where('missingReports', []));
    $this->actingAs(purchasing())->get(route('achats.referentiel'))
        ->assertInertia(fn (Assert $page) => $page->has('missingReports', 1)->where('missingReports.0.label', 'Treillis soudé ST25')->where('missingReports.0.requester', $chef->name));
});

it('RG8.15 : seul le Service Achats traite un signalement', function () {
    $report = MissingArticleReport::query()->create(['label' => 'Hydrofuge de masse', 'status' => 'a_traiter']);

    $this->actingAs(userWithRole(RoleName::ChefChantier))->patch(route('achats.referentiel.signalements.resolve', $report))->assertForbidden();
    expect($report->refresh()->status)->toBe('a_traiter');

    $buyer = purchasing();
    $this->actingAs($buyer)->patch(route('achats.referentiel.signalements.resolve', $report))->assertRedirect();
    expect($report->refresh()->status)->toBe('traite')->and($report->handled_by)->toBe($buyer->id);
});

it('valide le signalement et refuse les profils sans droit de saisie d\'achat', function () {
    $this->actingAs(userWithRole(RoleName::ChefChantier))->post(route('achats.referentiel.signalements.store'), ['label' => 'x'])->assertSessionHasErrors('label');
    $this->actingAs(userWithRole(RoleName::Comptabilite))->post(route('achats.referentiel.signalements.store'), ['label' => 'Treillis soudé'])->assertForbidden();
});
