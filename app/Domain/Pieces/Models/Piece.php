<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Policies\PiecePolicy;
use App\Domain\Projets\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Pièce commune : note de frais, recette ou pièce de caisse. Période d'imputation, n° de pièce,
 * imputation analytique (projet → lot → composante), lignes imputées à une rubrique budgétaire.
 *
 * @property int $id
 * @property PieceKind $kind
 * @property string $number
 * @property string $period
 * @property int $fiscal_year_id
 * @property string $title
 * @property int $requester_id
 * @property int|null $project_id
 * @property int|null $project_lot_id
 * @property int|null $lot_component_id
 * @property string|null $bc_number
 * @property string|null $observation
 * @property PieceStatus $status
 * @property int|null $current_step
 * @property int $total
 */
#[Fillable(['kind', 'number', 'period', 'fiscal_year_id', 'title', 'requester_id', 'project_id', 'project_lot_id', 'lot_component_id', 'bc_number', 'observation', 'status', 'current_step', 'total'])]
#[UsePolicy(PiecePolicy::class)]
class Piece extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => PieceKind::class,
            'status' => PieceStatus::class,
            'current_step' => 'integer',
            'total' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pieces')
            ->logOnly(['kind', 'number', 'period', 'title', 'status', 'total'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProjectLot::class, 'project_lot_id');
    }

    /** @return BelongsTo<LotComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(LotComponent::class, 'lot_component_id');
    }

    /** @return HasMany<PieceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PieceLine::class);
    }

    /** @return MorphMany<ValidationEvent, $this> */
    public function events(): MorphMany
    {
        return $this->morphMany(ValidationEvent::class, 'subject')->orderBy('id');
    }
}
