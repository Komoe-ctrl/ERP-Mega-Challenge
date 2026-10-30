<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pointage d'arrivée d'un utilisateur pour un jour donné.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $work_date
 * @property Carbon $arrived_at
 */
#[Fillable(['user_id', 'work_date', 'arrived_at'])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'arrived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
