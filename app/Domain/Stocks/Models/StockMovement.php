<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Models;

use App\Domain\Achats\Models\Article;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Stocks\Enums\MovementType;
use App\Domain\Stocks\Policies\StockPolicy;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mouvement de stock tracé. La quantité est toujours positive ; `direction` donne l'effet sur le
 * magasin d'origine (le transfert crédite en plus le magasin de destination).
 *
 * @property int $id
 * @property MovementType $type
 * @property int $warehouse_id
 * @property int|null $destination_warehouse_id
 * @property int $article_id
 * @property string $quantity
 * @property string $direction
 * @property int $unit_cost
 * @property int|null $chantier_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $reason
 * @property string $status
 * @property int|null $created_by
 * @property int|null $validated_by
 * @property CarbonInterface|null $validated_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['type', 'warehouse_id', 'destination_warehouse_id', 'article_id', 'quantity', 'direction', 'unit_cost', 'chantier_id', 'source_type', 'source_id', 'reason', 'status', 'created_by', 'validated_by', 'validated_at'])]
#[UsePolicy(StockPolicy::class)]
class StockMovement extends Model
{
    use LogsActivity;

    public const VALID = 'valide';

    public const PENDING = 'en_attente';

    public const REJECTED = 'rejete';

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'unit_cost' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    /** RG9.3 : pertes et ajustements visibles dans le journal des activités. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('stocks')
            ->logOnly(['type', 'warehouse_id', 'article_id', 'quantity', 'direction', 'status', 'reason'])
            ->logOnlyDirty();
    }

    public function qty(): float
    {
        return (float) $this->quantity;
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
