<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Événement du circuit d'une pièce : saisie, soumission, validation d'une étape, rejet.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $step_position
 * @property string $action
 * @property string|null $step_label
 * @property int|null $user_id
 * @property string|null $role_name
 * @property string|null $comment
 * @property Carbon $created_at
 */
#[Fillable(['subject_type', 'subject_id', 'step_position', 'action', 'step_label', 'user_id', 'role_name', 'comment'])]
class ValidationEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
