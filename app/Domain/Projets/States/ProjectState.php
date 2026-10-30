<?php

declare(strict_types=1);

namespace App\Domain\Projets\States;

use App\Domain\Projets\Models\Project;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Cycle de vie d'un projet (M4) : Étude → Devis → Appel d'offres → Marché →
 * Préparation → En cours → Réception → Clôturé. Un projet peut être abandonné
 * à tout statut antérieur à « Marché » (RG4.1).
 *
 * @extends State<Project>
 */
abstract class ProjectState extends State
{
    /** Parcours nominal, dans l'ordre. */
    public const JOURNEY = ['etude', 'devis', 'appel_offres', 'marche', 'preparation', 'en_cours', 'reception', 'cloture'];

    /** Statuts depuis lesquels l'abandon est possible (RG4.1). */
    public const ABANDONABLE = ['etude', 'devis', 'appel_offres'];

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Etude::class)
            ->allowTransition(Etude::class, Devis::class)
            ->allowTransition(Devis::class, AppelOffres::class)
            ->allowTransition(AppelOffres::class, Marche::class)
            ->allowTransition(Marche::class, Preparation::class)
            ->allowTransition(Preparation::class, EnCours::class)
            ->allowTransition(EnCours::class, Reception::class)
            ->allowTransition(Reception::class, Cloture::class)
            ->allowTransition([Etude::class, Devis::class, AppelOffres::class], Abandonne::class);
    }

    /** RG4.3 : un projet clôturé ou abandonné est en lecture seule. */
    public function isFinal(): bool
    {
        return in_array($this->getValue(), ['cloture', 'abandonne'], true);
    }

    /** Statut suivant du parcours nominal, ou null à la fin. */
    public function next(): ?string
    {
        $index = array_search($this->getValue(), self::JOURNEY, true);

        return is_int($index) ? (self::JOURNEY[$index + 1] ?? null) : null;
    }
}
