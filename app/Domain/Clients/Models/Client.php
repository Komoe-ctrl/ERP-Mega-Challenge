<?php

declare(strict_types=1);

namespace App\Domain\Clients\Models;

use App\Domain\Clients\Policies\ClientPolicy;
use App\Domain\Projets\Models\Project;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Client (M2). Fiche minimale en V1 ; la fiche complète arrive en V3.
 *
 * @property int $id
 * @property string $number
 * @property string $name
 * @property string $type
 * @property string|null $contact
 * @property string|null $phone
 * @property string|null $city
 */
#[Fillable(['number', 'name', 'type', 'contact', 'phone', 'city'])]
#[UsePolicy(ClientPolicy::class)]
#[UseFactory(ClientFactory::class)]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPES = ['particulier', 'entreprise', 'promoteur', 'administration', 'institution', 'societe_privee'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clients')
            ->logOnly(['number', 'name', 'type', 'contact', 'phone', 'city'])
            ->logOnlyDirty();
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
