<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Administration\Models\Agency;
use App\Domain\Clients\Models\Client;
use App\Domain\Projets\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'number' => 'PRJ-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => 'Projet '.fake()->unique()->word(),
            'client_id' => Client::factory(),
            'location' => 'Cocody, Abidjan',
            'manager_id' => User::factory(),
            'agency_id' => Agency::factory(),
            'budget' => 100_000_000,
            'start_date' => '2026-11-01',
            'end_date' => '2027-06-30',
        ];
    }

    /** Projet à un statut donné (écrit directement, sans passer par les transitions). */
    public function withStatus(string $status): self
    {
        return $this->afterCreating(function (Project $project) use ($status): void {
            $project->forceFill(['status' => $status])->saveQuietly();
        });
    }
}
