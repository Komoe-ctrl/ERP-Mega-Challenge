<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Administration\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('SV???')),
            'name' => 'Service '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
