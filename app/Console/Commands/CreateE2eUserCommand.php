<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Administration\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Crée (ou remet à jour) le compte du script de navigation e2e/navigation.mjs :
 * un compte qui voit tous les écrans, avec un rôle technique `e2e_complet`.
 * Ce rôle n'étant pas l'un des rôles qui imposent la double authentification
 * (Direction, Finance, Administrateur), la règle de sécurité reste intacte pour
 * les vrais comptes. Identifiants lus dans le .env (E2E_EMAIL, E2E_PASSWORD),
 * jamais écrits en dur. Refuse de s'exécuter hors de l'environnement local.
 */
final class CreateE2eUserCommand extends Command
{
    protected $signature = 'erp:e2e-user';

    protected $description = 'Crée le compte de test du script de navigation e2e (local uniquement)';

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->error('Commande réservée à l’environnement local.');

            return self::FAILURE;
        }

        $email = config('erp.e2e.email');
        $password = config('erp.e2e.password');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            $this->error('Renseignez E2E_EMAIL et E2E_PASSWORD dans le .env.');

            return self::FAILURE;
        }

        $role = Role::findOrCreate('e2e_complet', 'web');
        $role->syncPermissions(Permission::query()->get());

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = 'Compte e2e';
        $user->password = Hash::make($password);
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();
        $user->syncRoles([$role]);

        $this->info("Compte e2e prêt : {$email}");

        return self::SUCCESS;
    }
}
