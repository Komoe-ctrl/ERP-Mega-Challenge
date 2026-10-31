<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Enums;

/**
 * Opérations de stock du cahier des charges (M9).
 */
enum MovementType: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';
    case Transfert = 'transfert';
    case Inventaire = 'inventaire';
    case Retour = 'retour';
    case Perte = 'perte';
    case Ajustement = 'ajustement';

    public function label(): string
    {
        return match ($this) {
            self::Entree => 'Entrée',
            self::Sortie => 'Sortie',
            self::Transfert => 'Transfert',
            self::Inventaire => 'Inventaire',
            self::Retour => 'Retour',
            self::Perte => 'Perte',
            self::Ajustement => 'Ajustement',
        };
    }

    /** RG9.3 : les pertes et ajustements exigent un motif et une validation. */
    public function needsValidation(): bool
    {
        return $this === self::Perte || $this === self::Ajustement;
    }
}
