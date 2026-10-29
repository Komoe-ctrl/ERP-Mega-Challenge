<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Policies\FiscalYearPolicy;
use App\Models\User;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Exercice comptable. Un exercice clôturé est en lecture seule (RG1.4) :
 * la policy refuse toute modification et la clôture passe par l'action
 * CloseFiscalYear.
 *
 * @property int $id
 * @property string $label
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 */
#[Fillable(['label', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by'])]
#[UsePolicy(FiscalYearPolicy::class)]
#[UseFactory(FiscalYearFactory::class)]
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('administration')
            ->logOnly(['label', 'starts_on', 'ends_on', 'status', 'closed_by'])
            ->logOnlyDirty();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
