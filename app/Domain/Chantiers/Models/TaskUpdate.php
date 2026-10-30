<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mise à jour d'avancement d'une tâche (RG7.4), conservée en historique.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property int $progress_before
 * @property int $progress_after
 * @property string|null $comment
 */
#[Fillable(['task_id', 'user_id', 'progress_before', 'progress_after', 'comment'])]
class TaskUpdate extends Model
{
    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
