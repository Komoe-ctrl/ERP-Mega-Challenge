<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Enums;

use App\Domain\Shared\Enums\Module;

/**
 * Types de pièces qui passent par le circuit de validation et la saisie commune.
 */
enum PieceKind: string
{
    case NoteFrais = 'note_frais';
    case Recette = 'recette';
    case Caisse = 'caisse';

    public function label(): string
    {
        return match ($this) {
            self::NoteFrais => 'Note de frais',
            self::Recette => 'Recette',
            self::Caisse => 'Pièce de caisse',
        };
    }

    /** Module dont les permissions protègent ce type de pièce. */
    public function module(): Module
    {
        return match ($this) {
            self::NoteFrais => Module::Achats,
            self::Recette, self::Caisse => Module::Finance,
        };
    }

    /** Préfixe de numérotation automatique (RG1.3). */
    public function numberFormat(): string
    {
        return match ($this) {
            self::NoteFrais => 'NF-{YYYY}-{SEQ:4}',
            self::Recette => 'RE-{YYYY}-{SEQ:4}',
            self::Caisse => 'CA-{YYYY}-{SEQ:4}',
        };
    }
}
