<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use App\Domain\Projets\Models\Project;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produit / lot / section d'un projet (2ᵉ niveau de l'imputation analytique).
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 */
#[Fillable(['project_id', 'name'])]
class ProjectLot extends Model
{
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<LotComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(LotComponent::class);
    }
}
