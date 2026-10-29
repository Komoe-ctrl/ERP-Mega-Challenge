<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Policies\AgencyPolicy;
use App\Models\User;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $city
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'city', 'address', 'phone', 'is_active'])]
#[UsePolicy(AgencyPolicy::class)]
#[UseFactory(AgencyFactory::class)]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('administration')
            ->logOnly(['code', 'name', 'city', 'address', 'phone', 'is_active'])
            ->logOnlyDirty();
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
