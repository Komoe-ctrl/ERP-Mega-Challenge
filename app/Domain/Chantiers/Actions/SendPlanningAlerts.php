<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Chantiers\Support\PlanningCalculator;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Domain\Shared\Notifications\AlertRecipients;
use Carbon\CarbonInterface;

/**
 * Alertes quotidiennes de planning (RG7.6, RG7.7) : tâches en retard, à risque
 * ou non renseignées. Un seul message par chantier et par jour, adressé au chef
 * de chantier et au conducteur de travaux.
 */
final class SendPlanningAlerts
{
    private const LABELS = ['late' => 'en retard', 'risk' => 'à risque', 'stale' => 'non renseignée'];

    public function __construct(private readonly AlertDispatcher $alerts) {}

    /** @return int nombre de notifications envoyées */
    public function execute(?CarbonInterface $today = null): int
    {
        $today ??= now();
        $planning = PlanningCalculator::fromSettings();
        $sent = 0;

        Chantier::query()->whereIn('status', ['preparation', 'en_cours'])->with(['tasks', 'team'])->each(
            function (Chantier $chantier) use ($planning, $today, &$sent): void {
                $alerts = $chantier->tasks
                    ->map(fn (Task $t): array => [$t, $planning->health($t, $today)])
                    ->filter(fn (array $pair): bool => $planning->isAlert($pair[1]));

                if ($alerts->isEmpty()) {
                    return;
                }

                $lines = $alerts->take(3)->map(fn (array $pair): string => "{$pair[0]->name} ({$this->label($pair[1])})")->implode(', ');
                $more = $alerts->count() > 3 ? ' et '.($alerts->count() - 3).' autre(s)' : '';

                $sent += $this->alerts->send(
                    AlertRecipients::onChantier($chantier, Chantier::ROLE_CHEF, Chantier::ROLE_CONDUCTEUR),
                    'planning',
                    "Planning : {$alerts->count()} tâche(s) en alerte — {$chantier->name}",
                    "{$lines}{$more}.",
                    route('chantiers.show', $chantier, absolute: false),
                    "planning:{$chantier->id}",
                );
            },
        );

        return $sent;
    }

    private function label(string $health): string
    {
        return self::LABELS[$health] ?? $health;
    }
}
