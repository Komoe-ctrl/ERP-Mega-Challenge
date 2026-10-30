<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        return [
            'number' => 'DA-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'chantier_id' => Chantier::factory(),
            'requester_id' => User::factory(),
            'justification' => 'Ferraillage du plancher haut.',
        ];
    }

    /** Demande à un statut donné (écrit directement, sans passer par les transitions). */
    public function withStatus(string $status): self
    {
        return $this->afterCreating(function (PurchaseRequest $request) use ($status): void {
            $request->forceFill(['status' => $status])->saveQuietly();
        });
    }

    /**
     * Ajoute des lignes : [prix unitaire de l'article => quantité].
     *
     * @param  array<int, float|int>  $priceToQuantity
     */
    public function withLines(array $priceToQuantity): self
    {
        return $this->afterCreating(function (PurchaseRequest $request) use ($priceToQuantity): void {
            foreach ($priceToQuantity as $price => $quantity) {
                $request->lines()->create([
                    'article_id' => Article::factory()->create(['price' => $price])->id,
                    'quantity' => $quantity,
                    'need_date' => '2026-12-01',
                    'urgency' => 'normale',
                ]);
            }
        });
    }
}
