<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Administration\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2000, 2090);

        return [
            'label' => (string) $year,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'status' => FiscalYear::STATUS_OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => FiscalYear::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
