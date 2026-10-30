<?php

declare(strict_types=1);

namespace App\Domain\Achats\States;

use App\Domain\Achats\Models\PurchaseRequest;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Cycle de vie d une demande d achat (RG8.4) : Brouillon, En attente, puis
 * Validée, Refusée ou Renvoyée pour correction. L auteur peut l annuler tant
 * qu elle n est pas validée. Seul le passage à Validée fige les prix et
 * engage le budget (RG8.6).
 *
 * @extends State<PurchaseRequest>
 */
abstract class RequestState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Brouillon::class)
            ->allowTransition(Brouillon::class, EnAttente::class)
            ->allowTransition(Renvoyee::class, EnAttente::class)
            ->allowTransition(EnAttente::class, Validee::class)
            ->allowTransition(EnAttente::class, Refusee::class)
            ->allowTransition(EnAttente::class, Renvoyee::class)
            ->allowTransition([Brouillon::class, EnAttente::class, Renvoyee::class], Annulee::class);
    }

    /** Le demandeur peut encore corriger la demande. */
    public function isEditable(): bool
    {
        return in_array($this->getValue(), ['brouillon', 'renvoyee'], true);
    }

    /** Aucune modification n est plus possible. */
    public function isFinal(): bool
    {
        return in_array($this->getValue(), ['validee', 'refusee', 'annulee'], true);
    }
}
