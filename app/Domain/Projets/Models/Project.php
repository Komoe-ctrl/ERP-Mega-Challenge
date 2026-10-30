<?php

declare(strict_types=1);

namespace App\Domain\Projets\Models;

use App\Domain\Administration\Models\Agency;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Clients\Models\Client;
use App\Domain\Pieces\Models\ProjectLot;
use App\Domain\Projets\Policies\ProjectPolicy;
use App\Domain\Projets\States\ProjectState;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

/**
 * Projet (M4) : affaire liée à un client, de l'étude à la clôture.
 * Jamais supprimé : on l'abandonne (motif obligatoire) ou on le clôture.
 *
 * @property int $id
 * @property string $number
 * @property string $name
 * @property int $client_id
 * @property string $location
 * @property int $manager_id
 * @property int $agency_id
 * @property int $budget
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property ProjectState $status
 * @property string|null $abandon_reason
 * @property int|null $created_by
 */
#[Fillable(['number', 'name', 'client_id', 'location', 'manager_id', 'agency_id', 'budget', 'start_date', 'end_date', 'abandon_reason', 'created_by'])]
#[UsePolicy(ProjectPolicy::class)]
#[UseFactory(ProjectFactory::class)]
class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasStates, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'budget' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ProjectState::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('projets')
            ->logOnly(['number', 'name', 'client_id', 'location', 'manager_id', 'agency_id', 'budget', 'start_date', 'end_date', 'status', 'abandon_reason'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }

    /** RG4.3 : un projet clôturé ou abandonné est en lecture seule. */
    public function isReadOnly(): bool
    {
        return $this->status->isFinal();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<Chantier, $this> */
    public function chantiers(): HasMany
    {
        return $this->hasMany(Chantier::class);
    }

    /** @return HasMany<ProjectLot, $this> Produits / lots / sections (imputation analytique des pièces). */
    public function lots(): HasMany
    {
        return $this->hasMany(ProjectLot::class);
    }
}
