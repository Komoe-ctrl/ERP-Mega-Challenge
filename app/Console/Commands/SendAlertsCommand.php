<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Budget\Actions\SendBudgetAlerts;
use App\Domain\Chantiers\Actions\SendPlanningAlerts;
use Illuminate\Console\Command;

/**
 * Tâche planifiée quotidienne : alertes de planning (retard, risque, non
 * renseignée) et de budget (80 % et 100 %). Une alerte au plus par jour et par objet.
 */
final class SendAlertsCommand extends Command
{
    protected $signature = 'erp:send-alerts';

    protected $description = 'Envoie les alertes quotidiennes de planning et de budget';

    public function handle(SendPlanningAlerts $planning, SendBudgetAlerts $budget): int
    {
        $this->info("Planning : {$planning->execute()} notification(s).");
        $this->info("Budget : {$budget->execute()} notification(s).");

        return self::SUCCESS;
    }
}
