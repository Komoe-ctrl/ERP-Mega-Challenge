<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\MissingArticleReport;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Référentiel des prix de démonstration (repris des maquettes) avec un
 * historique de prix par article, et deux signalements d'articles manquants.
 */
class ArticlesSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = User::query()->where('name', 'Awa Koné')->value('id');

        foreach ($this->articles() as [$reference, $label, $category, $unit, $price]) {
            if (Article::query()->where('reference', $reference)->exists()) {
                continue;
            }

            $article = Article::query()->create([
                'reference' => $reference, 'label' => $label, 'category' => $category, 'unit' => $unit,
                'price' => $price, 'is_active' => $reference !== 'QUI-002',
            ]);

            $steps = [
                ['2026-01-10', (int) round($price * 0.93), 'Création de l\'article'],
                ['2026-04-02', (int) round($price * 0.97), 'Révision tarifaire fournisseur'],
                ['2026-09-15', $price, 'Mise à jour annuelle'],
            ];

            foreach ($steps as [$date, $amount, $reason]) {
                $history = $article->priceHistory()->create(['price' => $amount, 'user_id' => $buyer, 'reason' => $reason]);
                $history->forceFill(['created_at' => $date])->save();
            }
        }

        $reports = [
            ['Treillis soudé ST25 — panneau 2,4 × 6 m', 'Séraphin Konan', 'CH-001'],
            ['Hydrofuge de masse — bidon 20 L', 'Konan Yeboua', 'CH-003'],
        ];

        foreach ($reports as [$label, $requester, $chantier]) {
            MissingArticleReport::query()->firstOrCreate(['label' => $label], [
                'requester_id' => User::query()->where('name', $requester)->value('id'),
                'chantier_id' => Chantier::query()->where('code', $chantier)->value('id'),
                'status' => MissingArticleReport::STATUS_PENDING,
            ]);
        }
    }

    /**
     * @return list<array{string, string, string, string, int}>
     */
    private function articles(): array
    {
        return [
            ['CIM-001', 'Ciment CPJ 42,5 — sac 50 kg', 'Ciment', 'sac', 6_500],
            ['CIM-002', 'Ciment CPA 52,5 — sac 50 kg', 'Ciment', 'sac', 7_200],
            ['FER-008', 'Fer HA 8 — barre 12 m', 'Fer', 'barre', 3_100],
            ['FER-010', 'Fer HA 10 — barre 12 m', 'Fer', 'barre', 4_600],
            ['FER-012', 'Fer HA 12 — barre 12 m', 'Fer', 'barre', 6_600],
            ['FER-014', 'Fer HA 14 — barre 12 m', 'Fer', 'barre', 8_900],
            ['FER-020', 'Fil recuit', 'Fer', 'kg', 1_100],
            ['SAB-001', 'Sable de lagune', 'Sable', 'm³', 8_500],
            ['SAB-002', 'Sable de carrière', 'Sable', 'm³', 7_000],
            ['GRA-001', 'Gravier 5/15', 'Gravier', 'm³', 17_500],
            ['GRA-002', 'Gravier 15/25', 'Gravier', 'm³', 16_500],
            ['BOI-001', 'Planche de coffrage 3 m', 'Bois', 'pièce', 2_800],
            ['BOI-002', 'Chevron 8×8 — 4 m', 'Bois', 'pièce', 6_500],
            ['BOI-003', 'Contreplaqué marine 18 mm', 'Bois', 'panneau', 24_000],
            ['PEI-001', 'Peinture vinylique blanche — seau 25 L', 'Peinture', 'seau', 48_000],
            ['PEI-002', 'Peinture émail — pot 4 L', 'Peinture', 'pot', 21_500],
            ['PLO-001', 'Tuyau PVC Ø100 — 4 m', 'Plomberie', 'pièce', 7_800],
            ['PLO-002', 'Coude PVC Ø100', 'Plomberie', 'pièce', 1_900],
            ['PLO-003', 'Colle PVC — 1 kg', 'Plomberie', 'boîte', 6_000],
            ['ELE-001', 'Câble 2,5 mm² — couronne 100 m', 'Électricité', 'couronne', 38_000],
            ['ELE-002', 'Disjoncteur 20 A', 'Électricité', 'pièce', 4_500],
            ['ELE-003', 'Gaine ICTA Ø25 — couronne', 'Électricité', 'couronne', 9_500],
            ['QUI-001', 'Pointes 70 mm', 'Quincaillerie', 'kg', 1_400],
            ['QUI-002', 'Brouette renforcée', 'Quincaillerie', 'pièce', 32_000],
            ['MAC-001', 'Parpaing creux 15', 'Maçonnerie', 'unité', 450],
            ['MAC-002', 'Parpaing creux 20', 'Maçonnerie', 'unité', 600],
        ];
    }
}
