<?php

declare(strict_types=1);

namespace App\Domain\Clients\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Policies\ModulePolicy;

final class ClientPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Clients;
    }
}
