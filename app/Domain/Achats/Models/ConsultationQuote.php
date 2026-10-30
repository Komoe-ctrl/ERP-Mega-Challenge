<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Offre d'un fournisseur à une consultation : total HT et délai.
 *
 * @property int $id
 * @property int $consultation_id
 * @property int $supplier_id
 * @property int $total_amount
 * @property int $delay_days
 * @property bool $is_selected
 */
#[Fillable(['consultation_id', 'supplier_id', 'total_amount', 'delay_days', 'is_selected'])]
class ConsultationQuote extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['total_amount' => 'integer', 'delay_days' => 'integer', 'is_selected' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['consultation_id', 'supplier_id', 'total_amount', 'delay_days', 'is_selected'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
