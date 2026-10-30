<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\DailyReport;
use App\Domain\Projets\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Chantiers, équipes, planning et rapports de démonstration (repris des
 * maquettes). Dépend de ProjectsSeeder et DemoUsersSeeder : sans eux, rien n'est créé.
 */
class ChantiersSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->chantiers() as [$code, $name, $project, $location, $start, $end, $status, $conducteur, $chef, $storeman]) {
            $projectId = Project::query()->where('number', $project)->value('id');
            $conducteurId = User::query()->where('name', $conducteur)->value('id');
            $chefId = User::query()->where('name', $chef)->value('id');

            if ($projectId === null || $conducteurId === null || $chefId === null || Chantier::withTrashed()->where('code', $code)->exists()) {
                continue;
            }

            $chantier = Chantier::query()->create([
                'code' => $code, 'name' => $name, 'project_id' => $projectId, 'location' => $location,
                'start_date' => $start, 'end_date' => $end, 'status' => $status,
            ]);
            $chantier->team()->attach($conducteurId, ['role' => Chantier::ROLE_CONDUCTEUR]);
            $chantier->team()->attach($chefId, ['role' => Chantier::ROLE_CHEF]);

            if ($storeman !== null && ($storemanId = User::query()->where('name', $storeman)->value('id')) !== null) {
                $chantier->team()->attach($storemanId, ['role' => Chantier::ROLE_MAGASINIER]);
            }

            foreach ($this->tasks()[$code] ?? [] as [$taskName, $from, $to, $progress, $weight, $milestone, $lastUpdate, $comment]) {
                $chantier->tasks()->create([
                    'name' => $taskName, 'start_date' => $from, 'end_date' => $to, 'progress' => $progress,
                    'weight' => $weight, 'is_milestone' => $milestone, 'last_update_on' => $lastUpdate, 'comment' => $comment,
                ]);
            }

            foreach ($this->reports()[$code] ?? [] as [$date, $author, $works, $headcount, $received, $used, $incidents]) {
                DailyReport::query()->create([
                    'chantier_id' => $chantier->id, 'report_date' => $date,
                    'author_id' => User::query()->where('name', $author)->value('id'),
                    'works' => $works, 'headcount' => $headcount, 'materials_received' => $received,
                    'materials_used' => $used, 'incidents' => $incidents,
                ]);
            }
        }
    }

    /**
     * @return list<array{string, string, string, string, string, string, string, string, string, string|null}>
     */
    private function chantiers(): array
    {
        return [
            ['CH-001', 'Palmiers — Bloc A', 'PRJ-2026-001', 'Cocody Angré, Abidjan', '2026-06-01', '2027-02-28', 'en_cours', 'Ibrahim Coulibaly', 'Séraphin Konan', 'Adjoua Kra'],
            ['CH-002', 'Palmiers — Bloc B', 'PRJ-2026-001', 'Cocody Angré, Abidjan', '2026-07-06', '2027-03-31', 'en_cours', 'Ibrahim Coulibaly', 'Lanciné Touré', null],
            ['CH-003', 'École de Bingerville', 'PRJ-2026-003', 'Bingerville', '2026-11-02', '2027-07-31', 'preparation', 'Yao Kouassi', 'Konan Yeboua', null],
            ['CH-004', 'Palmiers — VRD', 'PRJ-2026-001', 'Cocody Angré, Abidjan', '2026-08-10', '2027-01-31', 'suspendu', 'Ibrahim Coulibaly', 'Ismaël Traoré', null],
            ['CH-005', 'Villa Marcory', 'PRJ-2025-011', 'Marcory, Abidjan', '2025-04-07', '2026-03-20', 'cloture', 'Ibrahim Coulibaly', 'Séraphin Konan', null],
        ];
    }

    /**
     * @return array<string, list<array{string, string, string, int, int, bool, string, string|null}>>
     */
    private function tasks(): array
    {
        return [
            'CH-001' => [
                ['Terrassement et fouilles', '2026-06-01', '2026-07-15', 100, 5, false, '2026-07-15', null],
                ['Fondations et longrines', '2026-07-01', '2026-08-31', 100, 10, false, '2026-08-31', null],
                ['Gros œuvre RDC et R+1', '2026-08-15', '2026-10-15', 90, 20, false, '2026-10-27', 'Retard de livraison du fer HA 12, rattrapage en cours.'],
                ['Plancher haut R+1', '2026-09-20', '2026-11-10', 55, 10, false, '2026-10-28', 'Pluies de la semaine dernière.'],
                ['Élévation R+2', '2026-10-10', '2026-12-05', 35, 12, false, '2026-10-28', null],
                ['Maçonnerie des cloisons', '2026-10-20', '2026-12-20', 5, 8, false, '2026-10-18', null],
                ['Plomberie — réseaux', '2026-11-01', '2027-01-15', 0, 8, false, '2026-10-10', null],
                ['Électricité', '2026-11-15', '2027-02-10', 0, 8, false, '2026-10-10', null],
                ['Carrelage et revêtements', '2027-01-10', '2027-02-28', 0, 10, false, '2026-10-10', null],
                ['Réception du gros œuvre', '2026-12-20', '2026-12-20', 0, 0, true, '2026-10-10', null],
            ],
            'CH-002' => [
                ['Terrassement', '2026-07-06', '2026-08-14', 100, 6, false, '2026-08-14', null],
                ['Fondations', '2026-08-10', '2026-10-10', 80, 14, false, '2026-10-26', 'Béton livré en retard, 2 semi-dalles à couler.'],
                ['Poteaux RDC', '2026-10-05', '2026-11-20', 30, 12, false, '2026-10-27', null],
                ['Plancher bas', '2026-11-10', '2026-12-30', 0, 12, false, '2026-10-12', null],
                ['Élévation R+1', '2027-01-05', '2027-03-10', 0, 16, false, '2026-10-12', null],
            ],
            'CH-003' => [
                ['Installation de chantier', '2026-11-02', '2026-11-20', 0, 4, false, '2026-10-25', null],
                ['Terrassement et plateforme', '2026-11-16', '2026-12-31', 0, 10, false, '2026-10-25', null],
                ['Fondations et soubassements', '2027-01-04', '2027-03-05', 0, 18, false, '2026-10-25', null],
            ],
            'CH-004' => [
                ['Caniveaux béton', '2026-08-10', '2026-10-05', 60, 15, false, '2026-09-22', 'Chantier suspendu : arrêt des travaux (saison des pluies).'],
                ['Réseau d\'assainissement', '2026-09-01', '2026-11-30', 20, 15, false, '2026-09-22', null],
            ],
        ];
    }

    /**
     * @return array<string, list<array{string, string, string, int, string, string, string}>>
     */
    private function reports(): array
    {
        return [
            'CH-001' => [
                ['2026-10-28', 'Séraphin Konan', 'Coulage du plancher haut R+1 (zone nord), ferraillage poteaux R+2 axes C à F.', 34, '12 t de fer HA 12 ; 280 sacs de ciment CPJ 42,5.', '9 m³ de béton ; 3,2 t de fer.', 'Pluie de 14 h à 16 h : arrêt du coulage 2 h.'],
                ['2026-10-27', 'Séraphin Konan', 'Coffrage plancher R+1 zone sud, maçonnerie cloisons RDC.', 31, '800 parpaings 15 ; 6 m³ de gravier 15/25.', '350 parpaings ; 40 sacs de ciment.', 'Aucun.'],
            ],
            'CH-002' => [
                ['2026-10-28', 'Lanciné Touré', 'Coulage fondations semelles S9 à S14.', 22, '15 m³ de béton prêt à l\'emploi.', '15 m³ de béton.', 'Retard du camion toupie (1 h 30).'],
            ],
        ];
    }
}
