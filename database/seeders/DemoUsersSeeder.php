<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\UserPoste;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Un utilisateur de démonstration par profil (section 4 du cahier), avec les
 * noms des données de démonstration de l'interface. Le mot de passe est celui
 * de ADMIN_PASSWORD. Ne s'exécute que si ERP_SEED_DEMO_USERS=true.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('erp.demo_admin.password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Renseignez ADMIN_PASSWORD dans le fichier .env avant de créer les comptes de démonstration.');
        }

        $agency = Agency::query()->where('code', 'ABJ')->value('id');

        foreach ($this->profiles() as [$name, $email, $role, $service]) {
            $user = User::query()->firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->fill([
                    'name' => $name,
                    'password' => $password,
                    'agency_id' => $agency,
                    'service_id' => Service::query()->where('code', $service)->value('id'),
                    'is_active' => true,
                ]);
                $user->email_verified_at = now();
                $user->save();
            }

            // Poste cohérent avec le rôle ; mis à jour aussi pour un compte déjà existant.
            $user->forceFill(['job_title' => $role->defaultJobTitle()])->save();

            $user->syncRoles([$role->value]);

            // Deux comptes occupent plusieurs postes : l'en-tête leur propose le sélecteur de poste.
            foreach ($this->extraPostes()[$email] ?? [] as [$title, $serviceCode]) {
                UserPoste::query()->updateOrCreate(['user_id' => $user->id, 'title' => $title], [
                    'service_id' => Service::query()->where('code', $serviceCode)->value('id'),
                    'is_default' => false,
                ]);
            }

            if (isset($this->extraPostes()[$email])) {
                UserPoste::query()->updateOrCreate(['user_id' => $user->id, 'title' => $role->defaultJobTitle()], [
                    'service_id' => $user->service_id,
                    'is_default' => true,
                ]);
            }
        }
    }

    /**
     * @return array<string, list<array{string, string}>> postes supplémentaires par e-mail (titre, code du service)
     */
    private function extraPostes(): array
    {
        return [
            'direction@megachallenge.local' => [['Directeur Mega Challenge', 'DG']],
            'conducteur@megachallenge.local' => [['Technicien 1', 'TRV']],
        ];
    }

    /**
     * @return list<array{string, string, RoleName, string}>
     */
    private function profiles(): array
    {
        return [
            ['Kouadio Brou', 'direction@megachallenge.local', RoleName::DirectionGenerale, 'DG'],
            ['Ibrahim Coulibaly', 'conducteur@megachallenge.local', RoleName::ConducteurTravaux, 'TRV'],
            ['Aminata Diabaté', 'direction.technique@megachallenge.local', RoleName::DirectionTechnique, 'DT'],
            ['Yao Kouassi', 'conducteur2@megachallenge.local', RoleName::ConducteurTravaux, 'TRV'],
            ['Awa Koné', 'achats@megachallenge.local', RoleName::ServiceAchatsBtp, 'ACH'],
            ['Séraphin Konan', 'chef@megachallenge.local', RoleName::ChefChantier, 'TRV'],
            ['Lanciné Touré', 'chef2@megachallenge.local', RoleName::ChefChantier, 'TRV'],
            ['Konan Yeboua', 'chef3@megachallenge.local', RoleName::ChefChantier, 'TRV'],
            ['Ismaël Traoré', 'chef4@megachallenge.local', RoleName::ChefChantier, 'TRV'],
            ['Adjoua Kra', 'magasin@megachallenge.local', RoleName::Magasinier, 'MAG'],
            ['Fanta Ouattara', 'comptabilite@megachallenge.local', RoleName::Comptabilite, 'CPT'],
            ['Moussa Diomandé', 'finance@megachallenge.local', RoleName::Finance, 'FIN'],
            ['Fatoumata Sylla', 'rh@megachallenge.local', RoleName::RessourcesHumaines, 'RH'],
        ];
    }
}
