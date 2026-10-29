<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * Macros de colonnes qui imposent les conventions de données :
 *
 *  - montants en FCFA : entier (BIGINT), jamais de décimales ni de FLOAT ;
 *  - quantités : DECIMAL, jamais FLOAT.
 *
 * Dans une migration : `$table->fcfa('montant_initial');`
 */
final class BlueprintMacros
{
    public const QUANTITY_PRECISION = 14;

    public const QUANTITY_SCALE = 3;

    public static function register(): void
    {
        if (Blueprint::hasMacro('fcfa')) {
            return;
        }

        Blueprint::macro('fcfa', function (string $column): ColumnDefinition {
            /** @var Blueprint $this */
            return $this->bigInteger($column);
        });

        Blueprint::macro('quantity', function (string $column): ColumnDefinition {
            /** @var Blueprint $this */
            return $this->decimal($column, BlueprintMacros::QUANTITY_PRECISION, BlueprintMacros::QUANTITY_SCALE);
        });
    }
}
