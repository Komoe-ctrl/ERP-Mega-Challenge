<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et modification d'un article du référentiel (RG8.12). Chaque
 * changement de prix est historisé avec son auteur et son motif (RG8.13).
 */
final class SaveArticle
{
    /**
     * @param  array{reference: string, label: string, category: string, unit: string, price: int}  $data
     */
    public function create(array $data, User $by): Article
    {
        return DB::transaction(function () use ($data, $by): Article {
            $article = Article::query()->create($data + ['is_active' => true]);
            $this->recordPrice($article, $by, 'Création de l\'article');

            return $article;
        });
    }

    /**
     * @param  array{reference: string, label: string, category: string, unit: string, price: int}  $data
     */
    public function update(Article $article, array $data, ?string $reason, User $by): Article
    {
        $priceChanged = $data['price'] !== $article->price;

        if ($priceChanged && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif du changement de prix.']);
        }

        return DB::transaction(function () use ($article, $data, $reason, $by, $priceChanged): Article {
            $article->update($data);

            if ($priceChanged) {
                $this->recordPrice($article, $by, trim((string) $reason));
            }

            return $article;
        });
    }

    public function recordPrice(Article $article, User $by, string $reason): void
    {
        $article->priceHistory()->create(['price' => $article->price, 'user_id' => $by->id, 'reason' => $reason]);
    }
}
