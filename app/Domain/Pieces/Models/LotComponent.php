<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composante d'un lot (3ᵉ niveau de l'imputation analytique).
 *
 * @property int $id
 * @property int $project_lot_id
 * @property string $name
 */
#[Fillable(['project_lot_id', 'name'])]
class LotComponent extends Model
{
    /** @return BelongsTo<ProjectLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProjectLot::class, 'project_lot_id');
    }
}
