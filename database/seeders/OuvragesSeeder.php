<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Achats\Models\Article;
use App\Domain\Devis\Models\Ouvrage;
use Illuminate\Database\Seeder;

/**
 * Bibliothèque d'ouvrages de démonstration (RG5.1). Rejouable : un ouvrage existant n'est pas recréé.
 * À jouer après le référentiel des articles.
 */
class OuvragesSeeder extends Seeder
{
    public function run(): void
    {
        $ouvrages = [
            ['Béton armé dosé à 350 kg/m³', 'm³', [['CIM-001', 7], ['SAB-001', 0.45], ['GRA-001', 0.85], ['FER-012', 11], [null, 1, 'Coffrage et main-d\'œuvre', 62_000]]],
            ['Maçonnerie de parpaings de 15', 'm²', [['MAC-001', 12.5], ['CIM-001', 0.35], ['SAB-001', 0.03], [null, 1, 'Main-d\'œuvre', 4_800]]],
            ['Peinture intérieure deux couches', 'm²', [['PEI-001', 0.05], [null, 1, 'Enduit, sous-couche et main-d\'œuvre', 1_900]]],
            ['Plomberie — évacuation PVC Ø100', 'ml', [['PLO-001', 0.25], ['PLO-002', 0.3], ['PLO-003', 0.02], [null, 1, 'Pose', 2_400]]],
        ];

        foreach ($ouvrages as [$label, $unit, $components]) {
            if (Ouvrage::query()->where('label', $label)->exists()) {
                continue;
            }

            $ouvrage = Ouvrage::query()->create(['label' => $label, 'unit' => $unit, 'is_active' => true]);

            foreach ($components as $component) {
                $reference = $component[0];

                $ouvrage->components()->create([
                    'article_id' => $reference !== null ? Article::query()->where('reference', $reference)->value('id') : null,
                    'label' => $component[2] ?? null,
                    'quantity' => $component[1],
                    'unit_price' => $component[3] ?? null,
                ]);
            }
        }
    }
}
