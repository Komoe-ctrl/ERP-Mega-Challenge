<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    public function definition(): array
    {
        return [
            'chantier_id' => Chantier::factory(),
            'post' => 'Gros œuvre',
            'nature' => 'materiaux',
            'initial_amount' => 50_000_000,
            'revised_amount' => 50_000_000,
        ];
    }
}
