<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Site;
use Illuminate\Database\Seeder;

/**
 * Organisation initiale : une agence (siège à Abidjan), les services et un
 * dépôt central. À compléter depuis l'application.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->firstOrCreate(
            ['code' => 'ABJ'],
            ['name' => 'Siège Abidjan', 'city' => 'Abidjan'],
        );

        $services = [
            'DG' => 'Direction générale',
            'DT' => 'Direction technique',
            'ACH' => 'Achats BTP',
            'TRV' => 'Travaux',
            'MAG' => 'Magasin',
            'CPT' => 'Comptabilité',
            'FIN' => 'Finance',
            'RH' => 'Ressources humaines',
            'ADM' => 'Administration',
        ];

        foreach ($services as $code => $name) {
            Service::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        Site::query()->firstOrCreate(
            ['code' => 'ABJ-SIEGE'],
            ['agency_id' => $agency->id, 'name' => 'Siège', 'type' => 'bureau'],
        );
        Site::query()->firstOrCreate(
            ['code' => 'ABJ-DEPOT'],
            ['agency_id' => $agency->id, 'name' => 'Dépôt central', 'type' => 'depot_central'],
        );
    }
}
