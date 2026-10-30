<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'number' => 'CLI-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => fake()->unique()->company(),
            'type' => fake()->randomElement(Client::TYPES),
            'contact' => fake()->name(),
            'phone' => '+225 07 00 00 00 00',
            'city' => 'Abidjan',
        ];
    }
}
