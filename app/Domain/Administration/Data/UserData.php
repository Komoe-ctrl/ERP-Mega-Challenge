<?php

declare(strict_types=1);

namespace App\Domain\Administration\Data;

final readonly class UserData
{
    /**
     * @param  list<string>  $roles  noms de rôles
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?int $agencyId,
        public ?int $serviceId,
        public ?string $phone,
        public bool $isActive,
        public array $roles,
        public ?string $password = null,
    ) {}
}
