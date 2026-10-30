<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Lecteur de la première feuille d un classeur : toutes les lignes, brutes.
 */
final class SheetRows implements ToArray
{
    /** @var array<array-key, mixed> */
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }
}
