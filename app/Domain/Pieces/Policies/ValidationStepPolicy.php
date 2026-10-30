<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Policies\ModulePolicy;

/** Le circuit de validation se paramètre dans Administration. */
final class ValidationStepPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Administration;
    }
}
