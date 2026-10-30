<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Support;

use App\Domain\Administration\Models\Setting;
use App\Domain\Chantiers\Models\Task;
use Carbon\CarbonInterface;

/**
 * Calculs du planning (RG7.5 à RG7.8), toujours faits côté serveur.
 * Les seuils viennent des paramètres (RG1.3) : 15 points et 7 jours par défaut.
 */
final readonly class PlanningCalculator
{
    public const HEALTHS = ['done', 'late', 'risk', 'stale', 'ok', 'upcoming'];

    public function __construct(
        public int $riskThresholdPoints = 15,
        public int $staleDays = 7,
    ) {}

    public static function fromSettings(): self
    {
        $risk = Setting::get('planning.risk_threshold_points', 15);
        $stale = Setting::get('planning.stale_days', 7);

        return new self(is_int($risk) ? $risk : 15, is_int($stale) ? $stale : 7);
    }

    /**
     * RG7.5 : avancement théorique = part du temps écoulé entre le début et la
     * fin prévue. Une tâche du 1er au 10, le 8 : 7 jours sur 9 = 78 %.
     */
    public function theoretical(Task $task, CarbonInterface $today): int
    {
        $start = $task->start_date->copy()->startOfDay();
        $end = $task->end_date->copy()->startOfDay();
        $day = $today->copy()->startOfDay();
        $total = (int) $start->diffInDays($end, false);

        if ($total <= 0) {
            return $day->greaterThanOrEqualTo($end) ? 100 : 0;
        }

        $elapsed = (int) $start->diffInDays($day, false);

        return (int) round(min(100, max(0, ($elapsed / $total) * 100)));
    }

    /**
     * RG7.6 : en retard si la fin prévue est dépassée sans atteindre 100 % ; à
     * risque si l'avancement réel est inférieur au théorique au-delà du seuil.
     * RG7.7 : « non renseignée » sans mise à jour depuis le délai paramétré.
     *
     * @return 'done'|'late'|'risk'|'stale'|'ok'|'upcoming'
     */
    public function health(Task $task, CarbonInterface $today): string
    {
        $day = $today->copy()->startOfDay();

        if ($task->progress >= 100) {
            return 'done';
        }

        if ($day->greaterThan($task->end_date->copy()->startOfDay())) {
            return 'late';
        }

        if ($this->theoretical($task, $today) - $task->progress > $this->riskThresholdPoints) {
            return 'risk';
        }

        if ($task->last_update_on->copy()->startOfDay()->diffInDays($day, false) > $this->staleDays) {
            return 'stale';
        }

        return $day->lessThan($task->start_date->copy()->startOfDay()) ? 'upcoming' : 'ok';
    }

    /**
     * RG7.8 : avancement global du chantier, pondéré par tâche.
     *
     * @param  iterable<Task>  $tasks
     */
    public function globalProgress(iterable $tasks): int
    {
        $weights = 0;
        $weighted = 0;

        foreach ($tasks as $task) {
            $weights += $task->weight;
            $weighted += $task->progress * $task->weight;
        }

        return $weights === 0 ? 0 : (int) round($weighted / $weights);
    }

    /** Une tâche « en alerte » est en retard, à risque ou non renseignée. */
    public function isAlert(string $health): bool
    {
        return in_array($health, ['late', 'risk', 'stale'], true);
    }
}
