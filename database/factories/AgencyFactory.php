<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Administration\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('AG???')),
            'name' => 'Agence '.fake()->unique()->city(),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}
