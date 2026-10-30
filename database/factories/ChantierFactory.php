<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Projets\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chantier>
 */
class ChantierFactory extends Factory
{
    protected $model = Chantier::class;

    public function definition(): array
    {
        return [
            'code' => 'CH-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => 'Chantier '.fake()->unique()->word(),
            'project_id' => Project::factory(),
            'location' => 'Cocody, Abidjan',
            'start_date' => '2026-11-01',
            'end_date' => '2027-03-31',
            'status' => 'preparation',
        ];
    }
}
