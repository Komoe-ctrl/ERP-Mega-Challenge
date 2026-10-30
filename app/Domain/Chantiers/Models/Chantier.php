<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Models;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Policies\ChantierPolicy;
use App\Domain\Projets\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\ChantierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Chantier (M7) : lieu d'exécution des travaux, rattaché à un projet.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $project_id
 * @property string $location
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property string $status
 */
#[Fillable(['code', 'name', 'project_id', 'location', 'start_date', 'end_date', 'status'])]
#[UsePolicy(ChantierPolicy::class)]
#[UseFactory(ChantierFactory::class)]
class Chantier extends Model
{
    /** @use HasFactory<ChantierFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUSES = ['preparation', 'en_cours', 'suspendu', 'receptionne', 'cloture'];

    public const ROLE_CONDUCTEUR = 'conducteur';

    public const ROLE_CHEF = 'chef_chantier';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('chantiers')
            ->logOnly(['code', 'name', 'project_id', 'location', 'start_date', 'end_date', 'status'])
            ->logOnlyDirty();
    }

    /** RG4.3 : lecture seule si le chantier est clôturé ou si son projet est clôturé ou abandonné. */
    public function isReadOnly(): bool
    {
        $project = $this->project;

        return $this->status === 'cloture' || $project === null || $project->isReadOnly();
    }

    /** RG4.2 : un responsable (conducteur de travaux) et un budget (déboursé sec) renseignés. */
    public function isReadyToStart(): bool
    {
        return $this->team()->wherePivot('role', self::ROLE_CONDUCTEUR)->exists()
            && $this->budgetLines()->exists();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chantier_user')->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<BudgetLine, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
