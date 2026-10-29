<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Policies\SitePolicy;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Site physique rattaché à une agence : siège, dépôt central, magasin de chantier.
 *
 * @property int $id
 * @property int $agency_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string|null $address
 * @property bool $is_active
 */
#[Fillable(['agency_id', 'code', 'name', 'type', 'address', 'is_active'])]
#[UsePolicy(SitePolicy::class)]
#[UseFactory(SiteFactory::class)]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, LogsActivity;

    public const TYPES = ['bureau', 'depot_central', 'magasin_chantier'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('administration')
            ->logOnly(['agency_id', 'code', 'name', 'type', 'address', 'is_active'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
