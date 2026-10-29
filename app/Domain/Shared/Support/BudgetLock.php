<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Transaction avec verrou de ligne pour toute validation qui touche un budget.
 *
 * Deux validations simultanées (ex. deux demandes d'achat sur le même chantier)
 * ne doivent jamais lire le même « reste à engager » : la seconde attend la
 * fin de la première puis relit la valeur à jour.
 *
 * Usage dans une Action :
 *
 *     return BudgetLock::run(
 *         Budget::query()->where('chantier_id', $chantier->id),
 *         function (Collection $budgets) use ($demande): Demande {
 *             // $budgets est verrouillé (SELECT ... FOR UPDATE) et à jour.
 *             // Vérifier le reste à engager, puis écrire l'engagement.
 *         },
 *     );
 *
 * Les lignes sont verrouillées dans l'ordre de leur clé primaire : cela évite
 * les interblocages (deadlocks) quand deux transactions verrouillent les
 * mêmes lignes dans un ordre différent.
 */
final class BudgetLock
{
    /**
     * @template TModel of Model
     * @template TReturn
     *
     * @param  Builder<TModel>  $budgets  requête désignant les lignes de budget à verrouiller
     * @param  Closure(Collection<int, TModel>): TReturn  $callback
     * @return TReturn
     */
    public static function run(Builder $budgets, Closure $callback, int $attempts = 3): mixed
    {
        return DB::transaction(function () use ($budgets, $callback) {
            $locked = $budgets
                ->clone()
                ->orderBy($budgets->getModel()->getQualifiedKeyName())
                ->lockForUpdate()
                ->get();

            return $callback($locked);
        }, $attempts);
    }
}
