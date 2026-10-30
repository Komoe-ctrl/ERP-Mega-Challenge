<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\Agency;
use App\Domain\Clients\Models\Client;
use App\Domain\Projets\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Clients et projets de démonstration (repris des maquettes). Les responsables
 * sont les comptes créés par DemoUsersSeeder : sans eux, rien n'est créé.
 */
class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->where('code', 'ABJ')->value('id');
        $clients = [];

        foreach ($this->clients() as [$number, $name, $type, $contact, $phone, $city]) {
            $clients[$name] = Client::query()->firstOrCreate(
                ['number' => $number],
                ['name' => $name, 'type' => $type, 'contact' => $contact, 'phone' => $phone, 'city' => $city],
            )->id;
        }

        foreach ($this->projects() as [$number, $name, $client, $location, $manager, $budget, $start, $end, $status, $reason]) {
            $managerId = User::query()->where('name', $manager)->value('id');

            if ($managerId === null || Project::withTrashed()->where('number', $number)->exists()) {
                continue;
            }

            $project = Project::query()->create([
                'number' => $number,
                'name' => $name,
                'client_id' => $clients[$client],
                'location' => $location,
                'manager_id' => $managerId,
                'agency_id' => $agency,
                'budget' => $budget,
                'start_date' => $start,
                'end_date' => $end,
                'abandon_reason' => $reason,
            ]);
            // Statut posé directement : les données de démonstration illustrent tout le parcours.
            $project->forceFill(['status' => $status])->saveQuietly();
        }
    }

    /**
     * @return list<array{string, string, string, string, string, string}>
     */
    private function clients(): array
    {
        return [
            ['CLI-0001', 'Immo Ivoire Promotion', 'promoteur', 'Fanta Ouattara', '+225 27 22 48 10 30', 'Abidjan, Cocody'],
            ['CLI-0002', 'Groupe Bâti-Finance', 'entreprise', 'Éric Gnahoré', '+225 27 20 21 33 00', 'Abidjan, Plateau'],
            ['CLI-0003', 'Ministère de l\'Éducation nationale', 'administration', 'Direction des infrastructures', '+225 27 20 22 05 11', 'Abidjan, Plateau'],
            ['CLI-0004', 'Sodiprim SA', 'societe_privee', 'Moussa Traoré', '+225 27 21 36 77 40', 'Abidjan, Yopougon'],
            ['CLI-0005', 'Société Foncière de la Riviera', 'promoteur', 'Aïcha Koffi', '+225 27 22 43 91 02', 'Abidjan, Riviera'],
            ['CLI-0006', 'M. Kouadio Brou', 'particulier', 'Kouadio Brou', '+225 07 07 12 90 45', 'Abidjan, Marcory'],
            ['CLI-0007', 'Mairie de Port-Bouët', 'institution', 'Service technique', '+225 27 21 27 00 10', 'Abidjan, Port-Bouët'],
            ['CLI-0008', 'Ministère de la Santé', 'administration', 'Direction des équipements', '+225 27 20 21 00 20', 'Abidjan, Plateau'],
        ];
    }

    /**
     * @return list<array{string, string, string, string, string, int, string, string, string, string|null}>
     */
    private function projects(): array
    {
        return [
            ['PRJ-2026-001', 'Résidence Les Palmiers — 24 logements', 'Immo Ivoire Promotion', 'Cocody Angré, Abidjan', 'Ibrahim Coulibaly', 485_000_000, '2026-06-01', '2027-04-30', 'en_cours', null],
            ['PRJ-2026-002', 'Siège Groupe Bâti-Finance — aménagement intérieur', 'Groupe Bâti-Finance', 'Plateau, Abidjan', 'Aminata Diabaté', 212_000_000, '2026-11-15', '2027-03-31', 'preparation', null],
            ['PRJ-2026-003', 'École primaire de Bingerville — 12 classes', 'Ministère de l\'Éducation nationale', 'Bingerville', 'Ibrahim Coulibaly', 410_000_000, '2026-11-02', '2027-07-31', 'marche', null],
            ['PRJ-2026-004', 'Entrepôt logistique Yopougon Zone industrielle', 'Sodiprim SA', 'Yopougon, Abidjan', 'Aminata Diabaté', 640_000_000, '2027-01-10', '2027-12-15', 'devis', null],
            ['PRJ-2026-005', 'Voirie et réseaux — lotissement Riviera 4', 'Société Foncière de la Riviera', 'Cocody Riviera, Abidjan', 'Yao Kouassi', 295_000_000, '2027-02-01', '2027-09-30', 'etude', null],
            ['PRJ-2026-006', 'Extension du marché de Port-Bouët', 'Mairie de Port-Bouët', 'Port-Bouët, Abidjan', 'Yao Kouassi', 380_000_000, '2027-03-01', '2027-11-30', 'appel_offres', null],
            ['PRJ-2025-011', 'Villa duplex Marcory Zone 4', 'M. Kouadio Brou', 'Marcory, Abidjan', 'Ibrahim Coulibaly', 96_000_000, '2025-04-07', '2026-03-20', 'cloture', null],
            ['PRJ-2026-007', 'Centre de santé communautaire d\'Abobo', 'Ministère de la Santé', 'Abobo, Abidjan', 'Aminata Diabaté', 175_000_000, '2026-09-01', '2027-05-31', 'abandonne', 'Appel d\'offres perdu : offre moins-disante retenue par le client.'],
        ];
    }
}
