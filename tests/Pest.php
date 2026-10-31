<?php

declare(strict_types=1);

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\RubriqueBudget;
use App\Domain\Pieces\Models\ValidationStep;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
