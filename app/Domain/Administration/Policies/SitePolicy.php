<?php

declare(strict_types=1);

namespace App\Domain\Administration\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Policies\ModulePolicy;

final class SitePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Administration;
    }
}
