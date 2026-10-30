<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rubrique budgétaire (ex. 601 « Matériaux de construction »).
 *
 * @property int $id
 * @property string $code
 * @property string $label
 * @property bool $is_active
 */
#[Fillable(['code', 'label', 'is_active'])]
class Rubrique extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<RubriqueBudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(RubriqueBudget::class);
    }
}
