<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Rapport journalier de chantier (RG7.9).
 *
 * @property int $id
 * @property int $chantier_id
 * @property CarbonInterface $report_date
 * @property int|null $author_id
 * @property string $works
 * @property int $headcount
 * @property string|null $materials_received
 * @property string|null $materials_used
 * @property string|null $incidents
 */
#[Fillable(['chantier_id', 'report_date', 'author_id', 'works', 'headcount', 'materials_received', 'materials_used', 'incidents'])]
class DailyReport extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['report_date' => 'date', 'headcount' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('chantiers')
            ->logOnly(['chantier_id', 'report_date', 'works', 'headcount', 'materials_received', 'materials_used', 'incidents'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
