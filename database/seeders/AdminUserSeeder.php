<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Compte administrateur de démonstration. Identifiants lus dans le .env
 * (ADMIN_EMAIL, ADMIN_PASSWORD), jamais écrits en dur.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('erp.demo_admin.email');
        $password = config('erp.demo_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException(
                'Renseignez ADMIN_EMAIL et ADMIN_PASSWORD dans le fichier .env avant de lancer le seeder.',
            );
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'name' => (string) config('erp.demo_admin.name'),
                'password' => $password,
                'agency_id' => Agency::query()->where('code', 'ABJ')->value('id'),
                'service_id' => Service::query()->where('code', 'ADM')->value('id'),
                'is_active' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        $user->assignRole(RoleName::Administrateur->value);
    }
}
