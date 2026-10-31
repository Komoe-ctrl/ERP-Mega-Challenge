<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Models;

use App\Domain\Stocks\Policies\StockPolicy;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Inventaire d'un magasin : le stock théorique est figé à l'ouverture, le stock compté est saisi par
 * article, l'écart est ajusté après validation (CA9).
 *
 * @property int $id
 * @property string $number
 * @property int $warehouse_id
 * @property string $status
 * @property int|null $opened_by
 * @property int|null $validated_by
 * @property CarbonInterface|null $validated_at
 */
#[Fillable(['number', 'warehouse_id', 'status', 'opened_by', 'validated_by', 'validated_at'])]
#[UsePolicy(StockPolicy::class)]
class Inventory extends Model
{
    use LogsActivity;

    public const OPEN = 'ouvert';

    public const VALIDATED = 'valide';

    protected function casts(): array
    {
        return ['validated_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('stocks')
            ->logOnly(['number', 'warehouse_id', 'status'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<InventoryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryLine::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
