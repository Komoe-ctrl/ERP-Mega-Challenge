<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Models;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Stocks\Policies\StockPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Magasin : dépôt central ou magasin de chantier (RG9.1).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $chantier_id
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'type', 'chantier_id', 'is_active'])]
#[UsePolicy(StockPolicy::class)]
class Warehouse extends Model
{
    public const CENTRAL = 'central';

    public const SITE = 'chantier';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Magasin du chantier, créé à la première réception ou au premier mouvement. */
    public static function forChantier(Chantier $chantier): self
    {
        return self::query()->firstOrCreate(
            ['chantier_id' => $chantier->id],
            ['code' => 'MAG-'.$chantier->code, 'name' => 'Magasin '.$chantier->name, 'type' => self::SITE, 'is_active' => true],
        );
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
