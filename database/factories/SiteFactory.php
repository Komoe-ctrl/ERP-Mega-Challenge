<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'code' => strtoupper(fake()->unique()->lexify('ST???')),
            'name' => 'Site '.fake()->unique()->word(),
            'type' => fake()->randomElement(Site::TYPES),
            'is_active' => true,
        ];
    }
}
