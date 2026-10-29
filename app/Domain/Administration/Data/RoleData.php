<?php

declare(strict_types=1);

namespace App\Domain\Administration\Data;

final readonly class RoleData
{
    /**
     * @param  list<string>  $permissions  noms `<module>.<action>`
     */
    public function __construct(
        public string $name,
        public array $permissions,
    ) {}
}
