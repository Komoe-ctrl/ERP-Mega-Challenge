<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Stocks\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Dépôts centraux de l'entreprise. Les magasins de chantier se créent d'eux-mêmes à la première
 * réception sur le chantier. Idempotent.
 */
class StocksSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'DEP-CENTRAL' => 'Dépôt central Abidjan',
            'DEP-YOP' => 'Dépôt Yopougon',
        ] as $code => $name) {
            Warehouse::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => Warehouse::CENTRAL, 'is_active' => true],
            );
        }
    }
}
