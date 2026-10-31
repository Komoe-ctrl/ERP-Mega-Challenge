<?php

declare(strict_types=1);

use App\Domain\Achats\Actions\CreatePurchaseOrder;
use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Budget\Models\BudgetCommitment;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\RubriqueBudget;
use App\Domain\Pieces\Models\ValidationStep;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuration Pest
|--------------------------------------------------------------------------
|
| Les tests Feature utilisent la base MySQL de test (`testing`), remise à zéro
| à chaque test. Les tests Unit n'ont pas besoin de la base.
|
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * Crée un utilisateur actif portant le profil donné (rôles et permissions semés).
 */
function userWithRole(RoleName $role, array $attributes = []): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create($attributes + ['is_active' => true]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Administrateur dont la double authentification est confirmée, pour ne pas
 * être redirigé vers la page de sécurité.
 */
function adminUser(): User
{
    return userWithRole(RoleName::Administrateur, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

// --- Aides des tests de pièces (circuit, exercice, rubrique, auteur) ---

/** Circuit par défaut : chef de service (Direction technique) puis Directeur (Direction générale). */
function defaultCircuit(): void
{
    ValidationStep::query()->create(['position' => 1, 'label' => 'Validation du chef de service', 'role_name' => RoleName::DirectionTechnique->value, 'button_label' => 'Chef de service', 'is_active' => true]);
    ValidationStep::query()->create(['position' => 2, 'label' => 'Approbation du Directeur', 'role_name' => RoleName::DirectionGenerale->value, 'button_label' => 'Directeur', 'is_active' => true]);
}

/** Exercice courant ouvert, une rubrique budgétée 1 000 000 FCFA. */
function pieceContext(): array
{
    $year = FiscalYear::factory()->create(['label' => (string) now()->year, 'starts_on' => now()->year.'-01-01', 'ends_on' => now()->year.'-12-31']);
    $rubrique = Rubrique::query()->create(['code' => '625', 'label' => 'Déplacements', 'is_active' => true]);
    RubriqueBudget::query()->create(['rubrique_id' => $rubrique->id, 'fiscal_year_id' => $year->id, 'amount' => 1_000_000]);

    return [$year, $rubrique];
}

function pieceUser(RoleName $role): User
{
    return userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function piecePayload(Rubrique $rubrique, array $overrides = []): array
{
    return array_merge([
        'period' => now()->format('Y-m'),
        'title' => 'Mission de suivi — Bouaké',
        'lines' => [
            ['designation' => 'Transport', 'quantity' => 2, 'unit_price' => 18_000, 'rubrique_id' => $rubrique->id],
            ['designation' => 'Hébergement', 'quantity' => 2, 'unit_price' => 35_000, 'rubrique_id' => $rubrique->id],
        ],
    ], $overrides);
}

function createPiece(User $author, Rubrique $rubrique, array $overrides = []): Piece
{
    test()->actingAs($author)->post(route('pieces.store', 'note_frais'), piecePayload($rubrique, $overrides))->assertSessionHasNoErrors();

    return Piece::query()->latest('id')->firstOrFail();
}

// --- Aides des tests d'achats (bon de commande ouvert, réception) ---

function receiver(RoleName $role = RoleName::ServiceAchatsBtp): User
{
    return userWithRole($role, ['two_factor_secret' => encrypt('s'), 'two_factor_recovery_codes' => encrypt(json_encode(['c'])), 'two_factor_confirmed_at' => now()]);
}

/**
 * Bon de commande de 100 unités à 10 000 FCFA (1 M), issu d'une demande validée dont
 * l'engagement est inscrit au budget du chantier.
 *
 * @return array{order: PurchaseOrder, line: BudgetLine, commitment: BudgetCommitment, supplier: Supplier, request: PurchaseRequest}
 */
function openOrder(?int $tariff = null): array
{
    test()->seed(SettingsSeeder::class);
    $chantier = Chantier::factory()->create(['status' => 'en_cours']);
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'nature' => 'materiaux', 'initial_amount' => 100_000_000, 'revised_amount' => 100_000_000, 'committed_amount' => 1_000_000]);
    $request = PurchaseRequest::factory()->withStatus('validee')->create(['chantier_id' => $chantier->id, 'budget_line_id' => $line->id, 'total_amount' => 1_000_000]);
    $article = Article::factory()->create(['price' => 10_000, 'category' => 'Ciment']);
    $request->lines()->create(['article_id' => $article->id, 'quantity' => 100, 'need_date' => '2026-12-01', 'urgency' => 'normale', 'frozen_price' => 10_000]);
    $commitment = BudgetCommitment::query()->create(['chantier_id' => $chantier->id, 'budget_line_id' => $line->id, 'source_type' => $request->getMorphClass(), 'source_id' => $request->id, 'amount' => 1_000_000, 'status' => 'active']);
    $supplier = Supplier::factory()->inCategories('Matériaux')->create();

    if ($tariff !== null) {
        SupplierTariff::query()->create(['supplier_id' => $supplier->id, 'article_id' => $article->id, 'price' => $tariff]);
    }

    $order = app(CreatePurchaseOrder::class)->direct($supplier, [['request_line_id' => $request->lines()->firstOrFail()->id, 'quantity' => 100]], receiver());

    return ['order' => $order->load('lines'), 'line' => $line, 'commitment' => $commitment, 'supplier' => $supplier, 'request' => $request];
}

/** Réception de la première ligne du bon : livré, renvoyé. */
function receive(PurchaseOrder $order, float $delivered, float $returned = 0, ?User $by = null): TestResponse
{
    return test()->actingAs($by ?? receiver())->post(route('achats.receptions.store', $order), [
        'lines' => [['purchase_order_line_id' => $order->lines()->firstOrFail()->id, 'delivered' => $delivered, 'returned' => $returned]],
    ]);
}
