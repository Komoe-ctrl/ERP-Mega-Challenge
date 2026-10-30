<?php

declare(strict_types=1);

namespace App\Domain\Achats\States;

final class EnAttente extends RequestState
{
    public static string $name = 'en_attente';
}
