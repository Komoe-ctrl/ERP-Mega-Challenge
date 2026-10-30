<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * RG7.4 : le chef de chantier renseigne l'avancement de chaque tâche en
 * pourcentage, avec commentaire et photos. Chaque mise à jour est conservée
 * en historique ; la date de dernière mise à jour alimente RG7.7.
 */
final class RecordProgress
{
    /**
     * @param  list<array{task_id: int, progress: int, comment: string|null, photos: list<UploadedFile>}>  $entries
     * @return int nombre de tâches mises à jour
     */
    public function execute(Chantier $chantier, User $by, array $entries): int
    {
        return DB::transaction(function () use ($chantier, $by, $entries): int {
            $tasks = $chantier->tasks()->where('is_milestone', false)->get()->keyBy('id');
            $updated = 0;

            foreach ($entries as $entry) {
                $task = $tasks->get($entry['task_id']);

                if ($task === null) {
                    continue;
                }

                $comment = $entry['comment'] !== null && trim($entry['comment']) !== '' ? trim($entry['comment']) : null;

                if ($entry['progress'] === $task->progress && $comment === null && $entry['photos'] === []) {
                    continue;
                }

                $before = $task->progress;

                $task->update([
                    'progress' => $entry['progress'],
                    'comment' => $comment ?? $task->comment,
                    'last_update_on' => now()->toDateString(),
                ]);
                $task->updates()->create([
                    'user_id' => $by->id,
                    'progress_before' => $before,
                    'progress_after' => $entry['progress'],
                    'comment' => $comment,
                ]);

                foreach ($entry['photos'] as $photo) {
                    $chantier->addMedia($photo)
                        ->withCustomProperties(['caption' => $task->name, 'task_id' => $task->id, 'author' => $by->name])
                        ->toMediaCollection('photos');
                }

                $updated++;
            }

            return $updated;
        });
    }
}
