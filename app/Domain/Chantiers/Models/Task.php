<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Models;

use Carbon\CarbonInterface;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tâche ou jalon du planning d'un chantier (RG7.3). Les droits passent par
 * la policy du chantier.
 *
 * @property int $id
 * @property int $chantier_id
 * @property string $name
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property int $progress
 * @property int $weight
 * @property bool $is_milestone
 * @property CarbonInterface $last_update_on
 * @property string|null $comment
 */
#[Fillable(['chantier_id', 'name', 'start_date', 'end_date', 'progress', 'weight', 'is_milestone', 'last_update_on', 'comment'])]
#[UseFactory(TaskFactory::class)]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'last_update_on' => 'date',
            'progress' => 'integer',
            'weight' => 'integer',
            'is_milestone' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('chantiers')
            ->logOnly(['chantier_id', 'name', 'start_date', 'end_date', 'progress', 'weight', 'is_milestone', 'comment'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return HasMany<TaskUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class);
    }
}
