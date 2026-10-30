<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Data;

/**
 * Saisie d'une pièce (formulaire commun).
 *
 * @phpstan-type Line array{designation: string, quantity: float|int|string, unit_price: int, rubrique_id: int}
 */
final readonly class PieceData
{
    /**
     * @param  list<array{designation: string, quantity: float|int|string, unit_price: int, rubrique_id: int}>  $lines
     */
    public function __construct(
        public string $period,
        public string $title,
        public ?string $number,
        public ?int $projectId,
        public ?int $lotId,
        public ?int $componentId,
        public ?string $bcNumber,
        public ?string $observation,
        public array $lines,
    ) {}
}
