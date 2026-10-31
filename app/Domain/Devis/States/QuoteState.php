<?php

declare(strict_types=1);

namespace App\Domain\Devis\States;

use App\Domain\Devis\Models\Quote;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Cycle de vie d'un devis (M5) : Brouillon → Soumis → Validé ou Refusé.
 * Seul le brouillon est modifiable ; un devis refusé se duplique en nouvelle version (RG5.3).
 *
 * @extends State<Quote>
 */
abstract class QuoteState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Brouillon::class)
            ->allowTransition(Brouillon::class, Soumis::class)
            ->allowTransition(Soumis::class, Valide::class)
            ->allowTransition(Soumis::class, Refuse::class);
    }
}
