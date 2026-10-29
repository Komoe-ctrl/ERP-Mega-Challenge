<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Administration\Models\FiscalYear;
use Illuminate\Database\Seeder;

/**
 * Exercice comptable de l'année en cours (1er janvier au 31 décembre).
 */
class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        FiscalYear::query()->firstOrCreate(
            ['label' => (string) $year],
            [
                'starts_on' => "{$year}-01-01",
                'ends_on' => "{$year}-12-31",
                'status' => FiscalYear::STATUS_OPEN,
            ],
        );
    }
}
