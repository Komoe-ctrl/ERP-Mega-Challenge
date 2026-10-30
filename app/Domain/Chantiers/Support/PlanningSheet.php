<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Support;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Lecteur de la première feuille d'un classeur de planning : toutes les lignes, brutes.
 */
final class PlanningSheet implements ToArray
{
    /** @var array<array-key, mixed> */
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }
}
