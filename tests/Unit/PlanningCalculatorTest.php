<?php

declare(strict_types=1);

use App\Domain\Chantiers\Models\Task;
use App\Domain\Chantiers\Support\PlanningCalculator;
use Illuminate\Support\Carbon;

// RG7.5 à RG7.8 et CA7.1 : calculs du planning, sans base de données.

function planningTask(string $start, string $end, int $progress, string $lastUpdate = '2026-11-01', int $weight = 10): Task
{
    return new Task([
        'start_date' => $start,
        'end_date' => $end,
        'progress' => $progress,
        'weight' => $weight,
        'last_update_on' => $lastUpdate,
    ]);
}

it('CA7.1 : une tâche du 1er au 10 renseignée à 30 % le 8 est « à risque » (théorique 78 %)', function () {
    $task = planningTask('2026-11-01', '2026-11-10', 30, lastUpdate: '2026-11-08');
    $today = Carbon::parse('2026-11-08');
    $planning = new PlanningCalculator;

    expect($planning->theoretical($task, $today))->toBe(78)
        ->and($planning->health($task, $today))->toBe('risk');
});

it('RG7.6 : une tâche est à risque au-delà du seuil seulement (15 points par défaut)', function () {
    $planning = new PlanningCalculator;
    $today = Carbon::parse('2026-11-08'); // théorique 78 %

    expect($planning->health(planningTask('2026-11-01', '2026-11-10', 63, '2026-11-08'), $today))->toBe('ok')
        ->and($planning->health(planningTask('2026-11-01', '2026-11-10', 62, '2026-11-08'), $today))->toBe('risk');
});

it('RG7.6 : une tâche dont la fin est dépassée sans atteindre 100 % est en retard', function () {
    $planning = new PlanningCalculator;

    expect($planning->health(planningTask('2026-11-01', '2026-11-10', 99), Carbon::parse('2026-11-11')))->toBe('late')
        ->and($planning->health(planningTask('2026-11-01', '2026-11-10', 99), Carbon::parse('2026-11-10')))->not->toBe('late')
        ->and($planning->health(planningTask('2026-11-01', '2026-11-10', 100), Carbon::parse('2026-11-30')))->toBe('done');
});

it('RG7.7 : une tâche sans mise à jour depuis plus de 7 jours est « non renseignée »', function () {
    $planning = new PlanningCalculator;
    // Avancement conforme au théorique (50 % le 5 sur 1→10, soit 44 %) : seule l'ancienneté joue.
    $task = planningTask('2026-11-01', '2026-11-10', 50, lastUpdate: '2026-10-28');

    expect($planning->health($task, Carbon::parse('2026-11-05')))->toBe('stale')
        ->and($planning->health($task, Carbon::parse('2026-11-04')))->toBe('ok');
});

it('distingue une tâche à venir d\'une tâche en cours', function () {
    $planning = new PlanningCalculator;
    $task = planningTask('2026-12-01', '2026-12-31', 0, lastUpdate: '2026-11-25');

    expect($planning->health($task, Carbon::parse('2026-11-26')))->toBe('upcoming')
        ->and($planning->theoretical($task, Carbon::parse('2026-11-26')))->toBe(0);
});

it('applique les seuils paramétrés', function () {
    $task = planningTask('2026-11-01', '2026-11-10', 60, lastUpdate: '2026-11-08');
    $today = Carbon::parse('2026-11-08'); // écart de 18 points

    expect((new PlanningCalculator(riskThresholdPoints: 15))->health($task, $today))->toBe('risk')
        ->and((new PlanningCalculator(riskThresholdPoints: 20))->health($task, $today))->toBe('ok');
});

it('un jalon d\'un jour passe à 100 % théorique à sa date', function () {
    $milestone = planningTask('2026-12-20', '2026-12-20', 0, lastUpdate: '2026-12-01', weight: 0);
    $planning = new PlanningCalculator;

    expect($planning->theoretical($milestone, Carbon::parse('2026-12-19')))->toBe(0)
        ->and($planning->theoretical($milestone, Carbon::parse('2026-12-20')))->toBe(100);
});

it('RG7.8 : l\'avancement global est pondéré par tâche', function () {
    $planning = new PlanningCalculator;
    $tasks = [
        planningTask('2026-11-01', '2026-11-10', 100, weight: 5),
        planningTask('2026-11-01', '2026-11-10', 50, weight: 15),
        planningTask('2026-11-01', '2026-11-10', 0, weight: 0),
    ];

    // (100×5 + 50×15) / 20 = 62,5 → 63
    expect($planning->globalProgress($tasks))->toBe(63)
        ->and($planning->globalProgress([]))->toBe(0);
});
