<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Paramètres par défaut (RG1.3). Valeurs modifiables ensuite sans développement ;
 * une valeur déjà modifiée n'est jamais réinitialisée.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as [$group, $key, $type, $value, $label, $description]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'type' => $type,
                    'value' => (string) $value,
                    'label' => $label,
                    'description' => $description,
                ],
            );
        }
    }

    /**
     * @return list<array{string, string, string, int|string, string, string|null}>
     */
    private function definitions(): array
    {
        return [
            ['validation', 'validation.direction_threshold_fcfa', 'integer', 5_000_000,
                'Seuil de validation Direction (FCFA)',
                'Au-delà de ce montant, une demande d\'achat doit être validée par la Direction (RG8.7).'],
            ['validation', 'validation.consultation_threshold_fcfa', 'integer', 2_000_000,
                'Seuil de consultation obligatoire (FCFA)',
                'Au-delà de ce montant, au moins trois fournisseurs sont consultés avant commande (RG8.11).'],
            ['validation', 'validation.consultation_min_suppliers', 'integer', 3,
                'Nombre minimal de fournisseurs consultés', null],

            ['alerts', 'budget.warning_percent', 'integer', 80,
                'Avertissement budgétaire (% du budget révisé)', 'RG10.3.'],
            ['alerts', 'budget.critical_percent', 'integer', 100,
                'Alerte budgétaire critique (% du budget révisé)', 'RG10.3.'],
            ['alerts', 'planning.risk_threshold_points', 'integer', 15,
                'Seuil « tâche à risque » (points d\'avancement)', 'RG7.6.'],
            ['alerts', 'planning.stale_days', 'integer', 7,
                'Délai « tâche non renseignée » (jours)', 'RG7.7.'],

            ['taxes', 'tax.vat_rate', 'decimal', '18',
                'Taux de TVA (%)', 'Taux appliqué aux devis et situations.'],

            ['numbering', 'numbering.devis', 'string', 'DEV-{YYYY}-{SEQ:5}',
                'Numérotation des devis', 'Variables : {YYYY} année, {SEQ:n} compteur sur n chiffres.'],
            ['numbering', 'numbering.bon_commande', 'string', 'BC-{YYYY}-{SEQ:5}',
                'Numérotation des bons de commande', null],
            ['numbering', 'numbering.situation', 'string', 'SIT-{YYYY}-{SEQ:4}',
                'Numérotation des situations de travaux', null],

            ['documents', 'documents.max_size_mb', 'integer', 20,
                'Taille maximale d\'une pièce jointe (Mo)', null],
        ];
    }
}
