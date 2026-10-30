<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'chantier_id' => Chantier::factory(),
            'name' => 'Tâche '.fake()->unique()->word(),
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'progress' => 0,
            'weight' => 10,
            'is_milestone' => false,
            'last_update_on' => '2026-11-01',
        ];
    }
}
