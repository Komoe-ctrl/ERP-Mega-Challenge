<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use App\Domain\Pieces\Policies\ValidationStepPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Étape du circuit de validation. Elle est portée par un rôle, jamais par un compte nommé.
 *
 * @property int $id
 * @property int $position
 * @property string $label
 * @property string $role_name
 * @property string $button_label
 * @property bool $is_active
 */
#[Fillable(['position', 'label', 'role_name', 'button_label', 'is_active'])]
#[UsePolicy(ValidationStepPolicy::class)]
class ValidationStep extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('administration')
            ->logOnly(['position', 'label', 'role_name', 'button_label', 'is_active'])
            ->logOnlyDirty();
    }
}
