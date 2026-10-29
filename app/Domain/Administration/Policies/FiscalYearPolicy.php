<?php

declare(strict_types=1);

namespace App\Domain\Administration\Policies;

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class FiscalYearPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Administration;
    }

    /** RG1.4 : un exercice clôturé est en lecture seule. */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof FiscalYear
            && ! $model->isClosed()
            && $this->allows($user, PermissionAction::Update);
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof FiscalYear
            && ! $model->isClosed()
            && $this->allows($user, PermissionAction::Delete);
    }

    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        return ! $fiscalYear->isClosed() && $this->allows($user, PermissionAction::Validate);
    }
}
