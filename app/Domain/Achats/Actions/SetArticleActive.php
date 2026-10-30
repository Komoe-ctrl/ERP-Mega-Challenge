<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\Article;
use App\Models\User;

/**
 * RG8.14 : un article est désactivé, jamais supprimé. Un article désactivé
 * n'est plus proposé dans les nouvelles demandes ; l'historique est conservé.
 * Le motif de désactivation est inscrit au journal.
 */
final class SetArticleActive
{
    public function execute(Article $article, bool $active, User $by, ?string $reason = null): void
    {
        $article->update(['is_active' => $active]);

        if (! $active && $reason !== null) {
            activity('achats')
                ->performedOn($article)
                ->causedBy($by)
                ->event('deactivation')
                ->withProperties(['motif' => $reason])
                ->log('Désactivation de l\'article');
        }
    }
}
