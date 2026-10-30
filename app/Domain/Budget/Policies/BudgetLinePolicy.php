<?php

declare(strict_types=1);

namespace App\Domain\Budget\Policies;

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Policies\ModulePolicy;

final class BudgetLinePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Budget;
    }
}
