<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Models;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Policies\ChantierPolicy;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\ChantierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
class Chantier extends Model implements HasMedia
{
    /** @use HasFactory<ChantierFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const STATUSES = ['preparation', 'en_cours', 'suspendu', 'receptionne', 'cloture'];

    public const ROLE_CONDUCTEUR = 'conducteur';

    public const ROLE_CHEF = 'chef_chantier';

    public const ROLE_MAGASINIER = 'magasinier';

    public const TEAM_ROLES = [self::ROLE_CONDUCTEUR, self::ROLE_CHEF, self::ROLE_MAGASINIER];

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
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

    /**
     * RG7.2 : les conducteurs de travaux et les chefs de chantier ne voient que
     * les chantiers auxquels ils sont affectés ; les autres profils voient tout.
     */
    public static function isScopedUser(User $user): bool
    {
        return $user->hasAnyRole([RoleName::ConducteurTravaux->value, RoleName::ChefChantier->value]);
    }

    public function isVisibleTo(User $user): bool
    {
        return ! self::isScopedUser($user) || $this->team()->where('users.id', $user->id)->exists();
    }

    /**
     * @param  Builder<Chantier>  $query
     * @return Builder<Chantier>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! self::isScopedUser($user)) {
            return $query;
        }

        return $query->whereHas('team', fn (Builder $q) => $q->where('users.id', $user->id));
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

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('start_date')->orderBy('id');
    }

    /** @return HasMany<DailyReport, $this> */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class)->orderByDesc('report_date');
    }
}
