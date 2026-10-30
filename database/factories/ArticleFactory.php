<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Achats\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'reference' => strtoupper(fake()->unique()->lexify('???')).'-'.fake()->unique()->numerify('###'),
            'label' => 'Article '.fake()->unique()->word(),
            'category' => fake()->randomElement(Article::CATEGORIES),
            'unit' => fake()->randomElement(Article::UNITS),
            'price' => fake()->numberBetween(500, 50_000),
            'is_active' => true,
        ];
    }
}
