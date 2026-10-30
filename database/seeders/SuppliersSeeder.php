<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Achats\Models\Article;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;
use Illuminate\Database\Seeder;

/**
 * Fournisseurs de démonstration (repris des maquettes), avec quelques tarifs
 * proches des prix de référence. Rejouable : un fournisseur existant est ignoré.
 */
class SuppliersSeeder extends Seeder
{
    public function run(): void
    {
        $sequence = 0;

        foreach ($this->suppliers() as [$name, $categories, $contact, $phone, $city]) {
            $sequence++;
            $supplier = Supplier::query()->firstOrCreate(
                ['name' => $name],
                ['number' => sprintf('FRS-%04d', $sequence), 'contact' => $contact, 'phone' => $phone, 'city' => $city],
            );

            foreach ($categories as $category) {
                $supplier->categories()->firstOrCreate(['category' => $category]);
            }
        }

        // Tarifs : un peu en dessous (ou au-dessus) du prix de référence, selon le fournisseur.
        foreach ([['Sotraci Matériaux', 'CIM-001', 0.97], ['Ciments & Fers du Golfe', 'CIM-001', 0.95], ['Ciments & Fers du Golfe', 'FER-012', 1.02], ['Afrique Aciers SA', 'FER-012', 0.98], ['Afrique Aciers SA', 'FER-010', 0.99]] as [$name, $reference, $ratio]) {
            $supplier = Supplier::query()->where('name', $name)->first();
            $article = Article::query()->where('reference', $reference)->first();

            if ($supplier !== null && $article !== null) {
                SupplierTariff::query()->firstOrCreate(
                    ['supplier_id' => $supplier->id, 'article_id' => $article->id],
                    ['price' => (int) round($article->price * $ratio)],
                );
            }
        }
    }

    /**
     * @return list<array{string, list<string>, string, string, string}>
     */
    private function suppliers(): array
    {
        return [
            ['Sotraci Matériaux', ['Matériaux'], 'Moussa Diomandé', '+225 27 21 35 11 20', 'Abidjan, Yopougon'],
            ['Ciments & Fers du Golfe', ['Matériaux'], 'Rokia Sanogo', '+225 27 21 24 80 05', 'Abidjan, Vridi'],
            ['Afrique Aciers SA', ['Matériaux'], 'Paul Aka', '+225 27 21 27 44 90', 'Abidjan, Koumassi'],
            ['Location Engins Atlantique', ['Engins'], 'Salif Cissé', '+225 07 08 55 12 34', 'Abidjan, Port-Bouët'],
            ['Transports Kouassi & Fils', ['Transport'], 'Yao Kouassi', '+225 05 06 77 19 02', 'Abidjan, Abobo'],
            ['Bâti-Électric CI', ['Matériaux', 'Prestations techniques'], 'Estelle N\'Dri', '+225 27 22 44 18 60', 'Abidjan, Marcory'],
            ['SOCOBAT Sous-traitance', ['Sous-traitance'], 'Ibrahima Fofana', '+225 07 47 90 31 15', 'Abidjan, Cocody'],
            ['Peintures Lagunes', ['Matériaux'], 'Clarisse Yapi', '+225 27 21 26 30 71', 'Abidjan, Treichville'],
        ];
    }
}
