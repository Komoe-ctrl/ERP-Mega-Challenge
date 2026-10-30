<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Domain\Achats\Policies\MissingArticleReportPolicy;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Signalement d'un article manquant (RG8.15), reçu par le Service Achats BTP.
 *
 * @property int $id
 * @property string $label
 * @property int|null $requester_id
 * @property int|null $chantier_id
 * @property string $status
 * @property int|null $handled_by
 * @property int|null $article_id
 */
#[Fillable(['label', 'requester_id', 'chantier_id', 'status', 'handled_by', 'article_id'])]
#[UsePolicy(MissingArticleReportPolicy::class)]
class MissingArticleReport extends Model
{
    use LogsActivity;

    public const STATUS_PENDING = 'a_traiter';

    public const STATUS_DONE = 'traite';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('achats')
            ->logOnly(['label', 'requester_id', 'chantier_id', 'status', 'handled_by', 'article_id'])
            ->logOnlyDirty();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<Chantier, $this> */
    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class);
    }
}
