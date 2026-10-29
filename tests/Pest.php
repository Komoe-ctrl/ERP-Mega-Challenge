<?php

declare(strict_types=1);

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
