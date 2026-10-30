<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'number' => 'FRS-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => fake()->unique()->company(),
            'contact' => fake()->name(),
            'phone' => '+225 07 00 00 00 00',
            'city' => 'Abidjan',
            'is_active' => true,
        ];
    }

    /** Fournisseur rattaché à des catégories. */
    public function inCategories(string ...$categories): self
    {
        return $this->afterCreating(function (Supplier $supplier) use ($categories): void {
            foreach ($categories as $category) {
                $supplier->categories()->create(['category' => $category]);
            }
        });
    }
}
