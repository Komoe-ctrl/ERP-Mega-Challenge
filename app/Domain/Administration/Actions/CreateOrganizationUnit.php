<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Site;

/**
 * Création d'une agence, d'un service ou d'un site (RG1.1, multi-sites).
 */
final class CreateOrganizationUnit
{
    /** @param array{code: string, name: string, city: string} $data */
    public function agency(array $data): Agency
    {
        return Agency::query()->create($data);
    }

    /** @param array{code: string, name: string} $data */
    public function service(array $data): Service
    {
        return Service::query()->create($data);
    }

    /** @param array{code: string, name: string, agency_id: int, type: string} $data */
    public function site(array $data): Site
    {
        return Site::query()->create($data);
    }
}
