<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Enums;

enum PieceStatus: string
{
    /** Brouillon de l'auteur : modifiable, pas encore soumis. */
    case Saisie = 'saisie';
    /** Soumise : attend l'étape `current_step` du circuit. */
    case EnValidation = 'en_validation';
    case Approuvee = 'approuvee';
    case Rejetee = 'rejetee';

    public function label(): string
    {
        return match ($this) {
            self::Saisie => 'Saisie',
            self::EnValidation => 'En validation',
            self::Approuvee => 'Approuvée',
            self::Rejetee => 'Rejetée',
        };
    }

    /** L'auteur peut encore modifier ou supprimer la pièce. */
    public function isEditable(): bool
    {
        return $this === self::Saisie || $this === self::Rejetee;
    }
}
