<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Budget\Models\BudgetExpense;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Models\BudgetRevision;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
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

        $this->operations();
    }

    /** Révisions validées et dépenses diverses de démonstration (montants déjà inclus dans les postes). */
    private function operations(): void
    {
        $chantiers = Chantier::query()->pluck('id', 'code');
        $users = User::query()->pluck('id', 'name');

        $revisions = [
            ['CH-001', 'Gros œuvre', 8_000_000, 'Avenant n°1 du marché : ajout d\'un voile de contreventement.', 'Ibrahim Coulibaly', 'Kouadio Brou'],
            ['CH-001', 'Main-d\'œuvre', 2_000_000, 'Heures supplémentaires pour rattraper le retard du plancher.', 'Ibrahim Coulibaly', 'Kouadio Brou'],
        ];

        foreach ($revisions as [$code, $post, $amount, $reason, $author, $decider]) {
            $line = BudgetLine::query()->where('chantier_id', $chantiers[$code] ?? 0)->where('post', $post)->first();

            if ($line === null || BudgetRevision::query()->where('budget_line_id', $line->id)->where('reason', $reason)->exists()) {
                continue;
            }

            BudgetRevision::query()->create([
                'chantier_id' => $line->chantier_id, 'budget_line_id' => $line->id, 'amount' => $amount, 'reason' => $reason,
                'requested_by' => $users[$author] ?? null, 'status' => BudgetRevision::VALIDATED,
                'decided_by' => $users[$decider] ?? null, 'decided_at' => '2026-09-30',
            ]);
        }

        $expenses = [
            ['CH-001', 'Engins et matériel', 'Location d\'une bétonnière (2 jours)', 90_000, '2026-10-28', 'facture-location-0412.pdf', 'Séraphin Konan'],
            ['CH-001', 'Gros œuvre', 'Transport de sable', 150_000, '2026-10-26', 'recu-transport-t-0089.pdf', 'Séraphin Konan'],
            ['CH-002', 'Second œuvre', 'Outillage (disques, gants)', 64_500, '2026-10-27', 'ticket-4471.pdf', 'Lanciné Touré'],
        ];

        foreach ($expenses as [$code, $post, $label, $amount, $date, $file, $author]) {
            $line = BudgetLine::query()->where('chantier_id', $chantiers[$code] ?? 0)->where('post', $post)->first();

            if ($line === null || BudgetExpense::query()->where('label', $label)->exists()) {
                continue;
            }

            $expense = BudgetExpense::query()->create([
                'chantier_id' => $line->chantier_id, 'budget_line_id' => $line->id, 'label' => $label,
                'amount' => $amount, 'spent_on' => $date, 'user_id' => $users[$author] ?? null,
            ]);
            $expense->addMediaFromString('Justificatif de démonstration')->usingFileName($file)->toMediaCollection('receipts');
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
