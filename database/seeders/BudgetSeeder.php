<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Database\Seeder;

/**
 * Déboursé sec de démonstration par poste (repris des maquettes, en millions de
 * FCFA) : CH-001 proche du seuil d'avertissement, CH-002 en dépassement, CH-003 au
 * démarrage, CH-004 suspendu. Les engagements des demandes validées sont inscrits
 * par PurchaseRequestsSeeder ; le reste correspond à des commandes antérieures (V2).
 */
class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $million = 1_000_000;

        foreach ($this->lines() as [$code, $post, $nature, $initial, $revised, $committed, $actual]) {
            $chantierId = Chantier::query()->where('code', $code)->value('id');

            if ($chantierId === null) {
                continue;
            }

            BudgetLine::query()->firstOrCreate(
                ['chantier_id' => $chantierId, 'post' => $post, 'nature' => $nature],
                [
                    'initial_amount' => $initial * $million,
                    'revised_amount' => $revised * $million,
                    'committed_amount' => $committed * $million,
                    'actual_amount' => $actual * $million,
                ],
            );
        }
    }

    /**
     * @return list<array{string, string, string, int, int, int, int}>
     */
    private function lines(): array
    {
        return [
            ['CH-001', 'Gros œuvre', 'materiaux', 120, 128, 14, 112],
            ['CH-001', 'Second œuvre', 'materiaux', 60, 60, 20, 20],
            ['CH-001', 'Lots techniques', 'materiaux', 45, 45, 9, 0],
            ['CH-001', 'Main-d\'œuvre', 'main_oeuvre', 70, 72, 0, 70],
            ['CH-001', 'Engins et matériel', 'materiel', 25, 25, 2, 20],
            ['CH-002', 'Gros œuvre', 'materiaux', 100, 100, 20, 105],
            ['CH-002', 'Second œuvre', 'materiaux', 50, 50, 38, 20],
            ['CH-002', 'Lots techniques', 'materiaux', 40, 40, 5, 2],
            ['CH-002', 'Main-d\'œuvre', 'main_oeuvre', 60, 60, 0, 62],
            ['CH-002', 'Engins et matériel', 'materiel', 20, 20, 1, 18],
            ['CH-003', 'Gros œuvre', 'materiaux', 150, 150, 0, 12],
            ['CH-003', 'Second œuvre', 'materiaux', 80, 80, 0, 0],
            ['CH-003', 'Lots techniques', 'materiaux', 60, 60, 0, 0],
            ['CH-003', 'Main-d\'œuvre', 'main_oeuvre', 90, 90, 0, 6],
            ['CH-003', 'Engins et matériel', 'materiel', 30, 30, 0, 4],
            ['CH-004', 'Terrassement et caniveaux', 'materiaux', 38, 38, 3, 14],
            ['CH-004', 'Main-d\'œuvre', 'main_oeuvre', 22, 22, 0, 9],
        ];
    }
}
