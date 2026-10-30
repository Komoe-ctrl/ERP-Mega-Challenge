<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Pieces\Models\LotComponent;
use App\Domain\Pieces\Models\ProjectLot;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\RubriqueBudget;
use App\Domain\Pieces\Models\ValidationStep;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use Illuminate\Database\Seeder;

/**
 * Circuit de validation par défaut (chef de service → Directeur), rubriques budgétaires, budget de
 * l'exercice ouvert et imputation analytique des projets existants. Idempotent : ne touche pas à
 * ce qui a déjà été paramétré.
 */
class PiecesSeeder extends Seeder
{
    public function run(): void
    {
        if (ValidationStep::query()->doesntExist()) {
            ValidationStep::query()->create([
                'position' => 1,
                'label' => 'Validation du chef de service',
                'role_name' => RoleName::DirectionTechnique->value,
                'button_label' => 'Chef de service',
                'is_active' => true,
            ]);
            ValidationStep::query()->create([
                'position' => 2,
                'label' => 'Approbation du Directeur Mega Challenge',
                'role_name' => RoleName::DirectionGenerale->value,
                'button_label' => 'Directeur Mega Challenge',
                'is_active' => true,
            ]);
        }

        $budgets = [
            ['601', 'Matériaux de construction', 180_000_000],
            ['604', 'Fournitures de chantier', 36_000_000],
            ['613', 'Location d\'engins', 48_000_000],
            ['622', 'Honoraires et prestations', 24_000_000],
            ['625', 'Déplacements et missions', 12_000_000],
            ['626', 'Frais postaux et télécommunications', 4_800_000],
            ['701', 'Situations de travaux facturées', 600_000_000],
        ];

        $year = FiscalYear::query()->where('status', FiscalYear::STATUS_OPEN)->orderBy('starts_on')->first();

        foreach ($budgets as [$code, $label, $amount]) {
            $rubrique = Rubrique::query()->firstOrCreate(['code' => $code], ['label' => $label, 'is_active' => true]);

            if ($year !== null) {
                RubriqueBudget::query()->firstOrCreate(
                    ['rubrique_id' => $rubrique->id, 'fiscal_year_id' => $year->id],
                    ['amount' => $amount],
                );
            }
        }

        foreach (Project::query()->get() as $project) {
            if ($project->lots()->exists()) {
                continue;
            }

            foreach ([
                'Lot 1 – Gros œuvre' => ['Fondations', 'Élévation', 'Dalles'],
                'Lot 2 – Second œuvre' => ['Plomberie', 'Électricité', 'Peinture'],
            ] as $name => $components) {
                $lot = ProjectLot::query()->create(['project_id' => $project->id, 'name' => $name]);

                foreach ($components as $component) {
                    LotComponent::query()->create(['project_lot_id' => $lot->id, 'name' => $component]);
                }
            }
        }
    }
}
