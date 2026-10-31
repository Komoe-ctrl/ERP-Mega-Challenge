<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Administration\Models\Activity;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Stocks\Models\Inventory;
use App\Domain\Stocks\Models\StockLevel;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

// M9 — Stocks BTP : RG9.1 à RG9.5 et CA9.

function stockUser(RoleName $role = RoleName::Magasinier): User
{
    return userWithRole($role, ['two_factor_secret' => encrypt('s'), 'two_factor_recovery_codes' => encrypt(json_encode(['c'])), 'two_factor_confirmed_at' => now()]);
}

function depot(string $name = 'Dépôt central'): Warehouse
{
    return Warehouse::query()->create(['code' => 'DEP-'.strtoupper(substr(md5($name), 0, 5)), 'name' => $name, 'type' => Warehouse::CENTRAL, 'is_active' => true]);
}

function movement(User $by, array $data): TestResponse
{
    return test()->actingAs($by)->post(route('stocks.mouvements.store'), $data);
}

function stockOf(Warehouse $warehouse, Article $article): float
{
    return (float) (StockLevel::query()->where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity') ?? 0);
}

function levelOf(Warehouse $warehouse, Article $article): StockLevel
{
    return StockLevel::query()->where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->firstOrFail();
}

/** Chantier avec un poste « matériaux » de 100 M. */
function siteWithBudget(): array
{
    $chantier = Chantier::factory()->create(['status' => 'en_cours']);
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'nature' => 'materiaux', 'initial_amount' => 100_000_000, 'revised_amount' => 100_000_000]);

    return [$chantier, $line];
}

// --- RG9.5 : coût moyen pondéré --------------------------------------------------------

it('RG9.5 : valorise le stock au coût moyen pondéré à chaque entrée', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();

    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000])->assertSessionHasNoErrors();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 2_000])->assertSessionHasNoErrors();

    $level = levelOf($depot, $article);
    expect($level->qty())->toBe(200.0)
        ->and($level->average_cost)->toBe(1_500)
        ->and($level->value())->toBe(300_000);
});

it('exige un coût unitaire pour une entrée', function () {
    $depot = depot();
    $article = Article::factory()->create();

    movement(stockUser(), ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10])->assertSessionHasErrors('unit_cost');

    expect(StockMovement::query()->count())->toBe(0);
});

it('refuse une quantité nulle ou négative', function () {
    $depot = depot();
    $article = Article::factory()->create();

    movement(stockUser(), ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 0, 'unit_cost' => 100])->assertSessionHasErrors('quantity');
    movement(stockUser(), ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => -5, 'unit_cost' => 100])->assertSessionHasErrors('quantity');
});

// --- RG9.2 : sortie imputée en coût réel sur le chantier ---------------------------------

it('RG9.2 : une sortie du dépôt central est imputée en coût réel sur le chantier, au coût moyen pondéré', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    [$chantier, $line] = siteWithBudget();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 30, 'chantier_id' => $chantier->id])->assertSessionHasNoErrors();

    expect(stockOf($depot, $article))->toBe(70.0)
        ->and($line->refresh()->actual_amount)->toBe(30_000);
});

it('RG9.2 : une sortie exige un chantier', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10])->assertSessionHasErrors('chantier_id');

    expect(stockOf($depot, $article))->toBe(100.0);
});

it('refuse une sortie supérieure au stock (jamais de stock négatif)', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    [$chantier, $line] = siteWithBudget();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 11, 'chantier_id' => $chantier->id])->assertSessionHasErrors('quantity');

    expect(stockOf($depot, $article))->toBe(10.0)->and($line->refresh()->actual_amount)->toBe(0);
});

it('refuse l\'imputation si le chantier n\'a pas de poste Matériaux, sans toucher au stock', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    $chantier = Chantier::factory()->create(['status' => 'en_cours']);
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'chantier_id' => $chantier->id])->assertSessionHasErrors('chantier_id');

    expect(stockOf($depot, $article))->toBe(10.0);
});

it('un retour au dépôt annule le coût imputé au chantier', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    [$chantier, $line] = siteWithBudget();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000]);
    movement($user, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 30, 'chantier_id' => $chantier->id]);

    movement($user, ['type' => 'retour', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'chantier_id' => $chantier->id])->assertSessionHasNoErrors();

    expect(stockOf($depot, $article))->toBe(80.0)->and($line->refresh()->actual_amount)->toBe(20_000);
});

// --- RG9.1 : un stock par magasin, transfert tracé ---------------------------------------

it('RG9.1 : chaque magasin a son propre stock et un transfert est un mouvement tracé', function () {
    $user = stockUser();
    $depot = depot();
    $site = depot('Magasin Palmiers');
    $article = Article::factory()->create();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'transfert', 'warehouse_id' => $depot->id, 'destination_warehouse_id' => $site->id, 'article_id' => $article->id, 'quantity' => 40])->assertSessionHasNoErrors();

    expect(stockOf($depot, $article))->toBe(60.0)
        ->and(stockOf($site, $article))->toBe(40.0)
        ->and(levelOf($site, $article)->average_cost)->toBe(1_000);

    $transfer = StockMovement::query()->where('type', 'transfert')->firstOrFail();
    expect($transfer->warehouse_id)->toBe($depot->id)->and($transfer->destination_warehouse_id)->toBe($site->id)->and($transfer->created_by)->toBe($user->id);
});

it('refuse un transfert vers le même magasin', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'transfert', 'warehouse_id' => $depot->id, 'destination_warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5])->assertSessionHasErrors('destination_warehouse_id');

    expect(stockOf($depot, $article))->toBe(10.0);
});

// --- RG9.3 : pertes et ajustements ------------------------------------------------------

it('RG9.3 : une perte exige un motif et attend la validation d\'un tiers, le stock ne bouge pas avant', function () {
    $user = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 50, 'unit_cost' => 1_000]);

    movement($user, ['type' => 'perte', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5])->assertSessionHasErrors('reason');
    movement($user, ['type' => 'perte', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'reason' => 'Sacs détrempés par la pluie.'])->assertSessionHasNoErrors();

    $loss = StockMovement::query()->where('type', 'perte')->firstOrFail();
    expect($loss->status)->toBe('en_attente')->and(stockOf($depot, $article))->toBe(50.0);

    $dg = stockUser(RoleName::DirectionGenerale);
    $this->actingAs($dg)->post(route('stocks.mouvements.validate', $loss))->assertSessionHasNoErrors();

    expect($loss->refresh()->status)->toBe('valide')->and($loss->validated_by)->toBe($dg->id)->and(stockOf($depot, $article))->toBe(45.0);
});

it('RG9.3 : l\'auteur d\'une perte ne la valide pas, et un magasinier ne valide pas (403)', function () {
    $magasinier = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 50, 'unit_cost' => 1_000]);
    movement($magasinier, ['type' => 'perte', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'reason' => 'Casse']);
    $loss = StockMovement::query()->where('type', 'perte')->firstOrFail();

    $this->actingAs($magasinier)->post(route('stocks.mouvements.validate', $loss))->assertForbidden();

    // Un valideur qui est aussi l'auteur : refusé par l'action (séparation des tâches).
    $dg = stockUser(RoleName::DirectionGenerale);
    $loss->update(['created_by' => $dg->id]);
    $this->actingAs($dg)->post(route('stocks.mouvements.validate', $loss))->assertSessionHasErrors('movement');

    expect($loss->refresh()->status)->toBe('en_attente')->and(stockOf($depot, $article))->toBe(50.0);
});

it('RG9.3 : un rejet motivé laisse le stock intact', function () {
    $magasinier = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 50, 'unit_cost' => 1_000]);
    movement($magasinier, ['type' => 'perte', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'reason' => 'Casse']);
    $loss = StockMovement::query()->where('type', 'perte')->firstOrFail();
    $dg = stockUser(RoleName::DirectionGenerale);

    $this->actingAs($dg)->post(route('stocks.mouvements.reject', $loss), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($dg)->post(route('stocks.mouvements.reject', $loss), ['reason' => 'Déjà déclarée.'])->assertSessionHasNoErrors();

    expect($loss->refresh()->status)->toBe('rejete')->and(stockOf($depot, $article))->toBe(50.0);
    // Une décision ne se rejoue pas.
    $this->actingAs($dg)->post(route('stocks.mouvements.validate', $loss))->assertSessionHasErrors('movement');
});

it('RG9.3 : un ajustement en plus ou en moins, après validation, et visible au journal des activités', function () {
    $magasinier = stockUser();
    $dg = stockUser(RoleName::DirectionGenerale);
    $depot = depot();
    $article = Article::factory()->create();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 50, 'unit_cost' => 1_000]);

    movement($magasinier, ['type' => 'ajustement', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'direction' => 'in', 'reason' => 'Retrouvé en vérifiant.'])->assertSessionHasNoErrors();
    movement($magasinier, ['type' => 'ajustement', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 2, 'direction' => 'out', 'reason' => 'Erreur de saisie.'])->assertSessionHasNoErrors();
    movement($magasinier, ['type' => 'ajustement', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 2, 'reason' => 'Sans sens.'])->assertSessionHasErrors('direction');

    foreach (StockMovement::query()->where('type', 'ajustement')->get() as $adjustment) {
        $this->actingAs($dg)->post(route('stocks.mouvements.validate', $adjustment));
    }

    expect(stockOf($depot, $article))->toBe(53.0)
        ->and(Activity::query()->where('log_name', 'stocks')->count())->toBeGreaterThan(0);
});

// --- RG9.4 : seuil minimal et alerte ----------------------------------------------------

it('RG9.4 : passer sous le seuil minimal alerte le magasinier et le Service Achats, une fois par jour', function () {
    $magasinier = stockUser();
    $achats = stockUser(RoleName::ServiceAchatsBtp);
    $depot = depot();
    $article = Article::factory()->create();
    [$chantier] = siteWithBudget();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000]);
    $this->actingAs($magasinier)->put(route('stocks.minimum', levelOf($depot, $article)), ['minimum' => 40])->assertSessionHasNoErrors();

    movement($stockKeeper = stockUser(), ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 50, 'chantier_id' => $chantier->id]);
    expect(DatabaseNotification::query()->where('notifiable_id', $achats->id)->count())->toBe(0);

    movement($stockKeeper, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 20, 'chantier_id' => $chantier->id]);
    expect(DatabaseNotification::query()->where('notifiable_id', $achats->id)->count())->toBe(1)
        ->and(data_get(DatabaseNotification::query()->where('notifiable_id', $achats->id)->first()?->data, 'kind'))->toBe('stock');

    // Une seconde sortie le même jour ne renvoie pas l'alerte.
    movement($stockKeeper, ['type' => 'sortie', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'chantier_id' => $chantier->id]);
    expect(DatabaseNotification::query()->where('notifiable_id', $achats->id)->count())->toBe(1);
});

it('le seuil minimal se règle avec le droit d\'écriture, et refuse une valeur négative', function () {
    $magasinier = stockUser();
    $depot = depot();
    $article = Article::factory()->create();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'unit_cost' => 1_000]);
    $level = levelOf($depot, $article);

    $this->actingAs($magasinier)->put(route('stocks.minimum', $level), ['minimum' => -1])->assertSessionHasErrors('minimum');
    $this->actingAs(stockUser(RoleName::ConducteurTravaux))->put(route('stocks.minimum', $level), ['minimum' => 5])->assertForbidden();
});

// --- Entrée en stock à la réception ----------------------------------------------------

it('une réception conforme entre en stock dans le magasin du chantier, au prix de la commande, sans recompter le coût', function () {
    ['order' => $order, 'line' => $budget] = openOrder();

    receive($order, 100)->assertSessionHasNoErrors();

    $warehouse = Warehouse::query()->where('chantier_id', $order->chantier_id)->firstOrFail();
    $article = Article::query()->firstOrFail();
    expect($warehouse->type)->toBe(Warehouse::SITE)
        ->and(stockOf($warehouse, $article))->toBe(100.0)
        ->and(levelOf($warehouse, $article)->average_cost)->toBe(10_000)
        ->and($budget->refresh()->actual_amount)->toBe(1_000_000);

    // Sortie vers le même chantier depuis son magasin : le coût a déjà été imputé à la réception.
    movement(stockUser(), ['type' => 'sortie', 'warehouse_id' => $warehouse->id, 'article_id' => $article->id, 'quantity' => 20, 'chantier_id' => $order->chantier_id])->assertSessionHasNoErrors();

    expect(stockOf($warehouse, $article))->toBe(80.0)->and($budget->refresh()->actual_amount)->toBe(1_000_000);
});

it('une réception partielle n\'entre en stock que les quantités acceptées', function () {
    ['order' => $order] = openOrder();

    receive($order, 60, 10)->assertSessionHasNoErrors();

    $warehouse = Warehouse::query()->where('chantier_id', $order->chantier_id)->firstOrFail();
    expect(stockOf($warehouse, Article::query()->firstOrFail()))->toBe(50.0);
});

it('un magasin de chantier ne sert que son chantier (sinon transfert)', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    $warehouse = Warehouse::query()->where('chantier_id', $order->chantier_id)->firstOrFail();
    [$other] = siteWithBudget();

    movement(stockUser(), ['type' => 'sortie', 'warehouse_id' => $warehouse->id, 'article_id' => Article::query()->firstOrFail()->id, 'quantity' => 10, 'chantier_id' => $other->id])->assertSessionHasErrors('chantier_id');
});

// --- CA9 : inventaire --------------------------------------------------------------------

it('CA9 : l\'inventaire fige le théorique, affiche l\'écart par article et ajuste après validation', function () {
    $magasinier = stockUser();
    $dg = stockUser(RoleName::DirectionGenerale);
    $depot = depot();
    $a = Article::factory()->create(['label' => 'Ciment']);
    $b = Article::factory()->create(['label' => 'Fer']);
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $a->id, 'quantity' => 100, 'unit_cost' => 1_000]);
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $b->id, 'quantity' => 50, 'unit_cost' => 2_000]);

    $this->actingAs($magasinier)->post(route('stocks.inventaire.store'), ['warehouse_id' => $depot->id])->assertSessionHasNoErrors();
    $inventory = Inventory::query()->firstOrFail();
    expect($inventory->number)->toStartWith('INV-'.now()->year.'-')->and($inventory->lines)->toHaveCount(2);

    $lines = $inventory->lines()->get()->keyBy('article_id');
    $this->actingAs($magasinier)->put(route('stocks.inventaire.count', $inventory), ['counted' => [$lines[$a->id]->id => 92, $lines[$b->id]->id => 50]])->assertSessionHasNoErrors();

    $this->actingAs($magasinier)->get(route('stocks.inventaire', ['inventaire' => $inventory->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.lines.0.theoretical', 100)
            ->where('selected.lines.0.counted', 92)
            ->where('selected.lines.0.gap', -8)
            ->where('selected.lines.1.gap', 0));

    // Rien n'est ajusté avant validation.
    expect(stockOf($depot, $a))->toBe(100.0);

    $this->actingAs($dg)->post(route('stocks.inventaire.validate', $inventory))->assertSessionHasNoErrors();

    expect(stockOf($depot, $a))->toBe(92.0)->and(stockOf($depot, $b))->toBe(50.0)
        ->and($inventory->refresh()->status)->toBe('valide')
        ->and(StockMovement::query()->where('type', 'inventaire')->count())->toBe(1);
});

it('CA9 : celui qui a ouvert l\'inventaire ne le valide pas, un seul inventaire ouvert par magasin', function () {
    $magasinier = stockUser();
    $dg = stockUser(RoleName::DirectionGenerale);
    $depot = depot();
    $article = Article::factory()->create();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'unit_cost' => 1_000]);

    $this->actingAs($magasinier)->post(route('stocks.inventaire.store'), ['warehouse_id' => $depot->id])->assertSessionHasNoErrors();
    $this->actingAs($magasinier)->post(route('stocks.inventaire.store'), ['warehouse_id' => $depot->id])->assertSessionHasErrors('warehouse_id');

    $inventory = Inventory::query()->firstOrFail();
    $line = $inventory->lines()->firstOrFail();
    $this->actingAs($magasinier)->put(route('stocks.inventaire.count', $inventory), ['counted' => [$line->id => 9]]);

    // Le magasinier ne valide pas (403) ; un valideur qui a ouvert l'inventaire non plus (séparation des tâches).
    $this->actingAs($magasinier)->post(route('stocks.inventaire.validate', $inventory))->assertForbidden();
    $inventory->update(['opened_by' => $dg->id]);
    $this->actingAs($dg)->post(route('stocks.inventaire.validate', $inventory))->assertSessionHasErrors('inventory');

    expect(stockOf($depot, $article))->toBe(10.0);
});

it('CA9 : valider sans aucune quantité comptée est refusé', function () {
    $dg = stockUser(RoleName::DirectionGenerale);
    $depot = depot();
    $article = Article::factory()->create();
    movement(stockUser(), ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 10, 'unit_cost' => 1_000]);
    $this->actingAs(stockUser())->post(route('stocks.inventaire.store'), ['warehouse_id' => $depot->id]);

    $this->actingAs($dg)->post(route('stocks.inventaire.validate', Inventory::query()->firstOrFail()))->assertSessionHasErrors('inventory');
});

it('l\'écart d\'inventaire se calcule sur le stock courant : un mouvement après l\'ouverture n\'est pas écrasé à tort', function () {
    $magasinier = stockUser();
    $dg = stockUser(RoleName::DirectionGenerale);
    $depot = depot();
    $article = Article::factory()->create();
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 100, 'unit_cost' => 1_000]);
    $this->actingAs($magasinier)->post(route('stocks.inventaire.store'), ['warehouse_id' => $depot->id]);
    $inventory = Inventory::query()->firstOrFail();
    $line = $inventory->lines()->firstOrFail();

    // Entrée de 20 après l'ouverture, puis comptage de 120 : aucun écart à ajuster.
    movement($magasinier, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 20, 'unit_cost' => 1_000]);
    $this->actingAs($magasinier)->put(route('stocks.inventaire.count', $inventory), ['counted' => [$line->id => 120]]);
    $this->actingAs($dg)->post(route('stocks.inventaire.validate', $inventory))->assertSessionHasNoErrors();

    expect(stockOf($depot, $article))->toBe(120.0)->and(StockMovement::query()->where('type', 'inventaire')->count())->toBe(0);
});

// --- Droits et écrans ---------------------------------------------------------------------

it('refuse les écrans de stock à un profil sans droit (403) et l\'écriture à un profil en lecture seule', function () {
    $depot = depot();
    $article = Article::factory()->create();

    $this->actingAs(stockUser(RoleName::RessourcesHumaines))->get(route('stocks.index'))->assertForbidden();
    $this->actingAs(stockUser(RoleName::Administrateur))->get(route('stocks.mouvements'))->assertForbidden();
    // Le conducteur lit les stocks mais ne saisit pas.
    $this->actingAs(stockUser(RoleName::ConducteurTravaux))->get(route('stocks.index'))->assertOk();
    movement(stockUser(RoleName::ConducteurTravaux), ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $article->id, 'quantity' => 5, 'unit_cost' => 100])->assertForbidden();
});

it('affiche l\'état des stocks valorisé, par magasin, avec les articles sous le seuil', function () {
    $user = stockUser();
    $depot = depot();
    $a = Article::factory()->create();
    $b = Article::factory()->create();
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $a->id, 'quantity' => 10, 'unit_cost' => 1_000]);
    movement($user, ['type' => 'entree', 'warehouse_id' => $depot->id, 'article_id' => $b->id, 'quantity' => 4, 'unit_cost' => 500]);
    levelOf($depot, $b)->update(['minimum' => 5]);

    $this->actingAs($user)->get(route('stocks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Stocks/Index', false)
            ->has('items', 2)
            ->where('totals.value', 12_000)
            ->where('totals.low', 1)
            ->where('can.configure', true));

    $this->actingAs($user)->get(route('stocks.mouvements'))
        ->assertInertia(fn (Assert $page) => $page->component('Stocks/Mouvements', false)->has('movements.data', 2)->has('types', 6)->where('types.0.value', 'entree')->where('can.record', true));
});
