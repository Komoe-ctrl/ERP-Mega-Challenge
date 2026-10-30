<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Administration\Models\Role;
use App\Domain\Administration\Models\Service;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Permission;

/**
 * Crée (ou remet à jour) les comptes du script de navigation e2e/navigation.mjs :
 *  - un compte par profil (`e2e.<profil>@megachallenge.local`), avec son vrai rôle ;
 *    la double authentification est activée pour les profils qui l'imposent (la
 *    règle de sécurité reste donc intacte), avec un secret TOTP connu du script ;
 *  - un compte technique `e2e_complet` qui voit tous les écrans.
 * Identifiants lus dans le .env (E2E_EMAIL, E2E_PASSWORD, E2E_TOTP_SECRET), jamais
 * écrits en dur. Refuse de s'exécuter hors de l'environnement local.
 */
final class CreateE2eUserCommand extends Command
{
    protected $signature = 'erp:e2e-user';

    protected $description = 'Crée les comptes de test du script de navigation e2e (local uniquement)';

    /** Service de rattachement de chaque profil. */
    private const SERVICES = [
        'administrateur' => 'ADM',
        'direction_generale' => 'DG',
        'direction_technique' => 'DT',
        'service_achats_btp' => 'ACH',
        'conducteur_travaux' => 'TRV',
        'chef_chantier' => 'TRV',
        'magasinier' => 'MAG',
        'comptabilite' => 'CPT',
        'finance' => 'FIN',
        'ressources_humaines' => 'RH',
    ];

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->error('Commande réservée à l’environnement local.');

            return self::FAILURE;
        }

        $email = config('erp.e2e.email');
        $password = config('erp.e2e.password');
        $secret = config('erp.e2e.totp_secret');

        if (! is_string($email) || ! is_string($password) || ! is_string($secret) || $email === '' || $password === '' || $secret === '') {
            $this->error('Renseignez E2E_EMAIL, E2E_PASSWORD et E2E_TOTP_SECRET (base32) dans le .env.');

            return self::FAILURE;
        }

        $complete = Role::findOrCreate('e2e_complet', 'web');
        $complete->syncPermissions(Permission::query()->get());
        $this->account($email, 'Compte e2e', $password, $complete, 'Testeur', null, null);

        [$local, $domain] = explode('@', $email) + [1 => 'megachallenge.local'];

        foreach (RoleName::cases() as $role) {
            $this->account(
                "{$local}.{$role->value}@{$domain}",
                'E2E '.$role->label(),
                $password,
                Role::findByName($role->value, 'web'),
                $role->defaultJobTitle(),
                self::SERVICES[$role->value],
                $role->requiresTwoFactor() ? $secret : null,
            );
        }

        $this->info('Comptes e2e prêts (un par profil + e2e_complet).');

        return self::SUCCESS;
    }

    private function account(string $email, string $name, string $password, RoleContract $role, string $jobTitle, ?string $serviceCode, ?string $totpSecret): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->is_active = true;
        $user->job_title = $jobTitle;
        $serviceId = $serviceCode === null ? null : Service::query()->where('code', $serviceCode)->value('id');
        $user->service_id = is_int($serviceId) ? $serviceId : null;
        $user->email_verified_at = now();
        $user->two_factor_secret = $totpSecret === null ? null : encrypt($totpSecret);
        $user->two_factor_recovery_codes = $totpSecret === null ? null : encrypt(json_encode([]));
        $user->two_factor_confirmed_at = $totpSecret === null ? null : now();
        $user->save();
        $user->syncRoles([$role]);
    }
}
