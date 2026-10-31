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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Client (M2) : coordonnées, contacts, documents, projets associés.
 *
 * @property int $id
 * @property string $number
 * @property string $name
 * @property string $type
 * @property string|null $contact
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $city
 * @property string|null $address
 */
#[Fillable(['number', 'name', 'type', 'contact', 'phone', 'email', 'city', 'address'])]
#[UsePolicy(ClientPolicy::class)]
#[UseFactory(ClientFactory::class)]
class Client extends Model implements HasMedia
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const TYPES = ['particulier', 'entreprise', 'promoteur', 'administration', 'institution', 'societe_privee'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clients')
            ->logOnly(['number', 'name', 'type', 'contact', 'phone', 'email', 'city', 'address'])
            ->logOnlyDirty();
    }

    /** Documents du client (contrats, pièces d'identité, attestations…). */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderBy('id');
    }
}
