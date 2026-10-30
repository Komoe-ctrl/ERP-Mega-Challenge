<?php

declare(strict_types=1);

namespace App\Domain\Budget\Actions;

use App\Domain\Budget\Support\BudgetSummary;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Domain\Shared\Notifications\AlertRecipients;

/**
 * Alertes quotidiennes de budget (RG10.3) : avertissement à 80 %, alerte
 * critique à 100 % du budget révisé consommé (seuils paramétrables). Un seul
 * message par chantier et par jour, adressé aux conducteurs de travaux du
 * chantier et à la Direction technique.
 */
final class SendBudgetAlerts
{
    public function __construct(private readonly AlertDispatcher $alerts) {}

    /** @return int nombre de notifications envoyées */
    public function execute(): int
    {
        $sent = 0;
        $direction = AlertRecipients::withRoles(RoleName::DirectionTechnique);

        Chantier::query()->whereHas('budgetLines')->with('budgetLines')->each(function (Chantier $chantier) use ($direction, &$sent): void {
            $summary = BudgetSummary::fromLines($chantier->budgetLines);
            $level = $summary->level();

            if ($level === 'ok') {
                return;
            }

            $percent = number_format($summary->consumption(), 1, ',', ' ');
            $sent += $this->alerts->send(
                AlertRecipients::onChantier($chantier, Chantier::ROLE_CONDUCTEUR)->merge($direction),
                'budget',
                $level === 'critical' ? 'Alerte budgétaire critique' : 'Avertissement budgétaire',
                "{$chantier->name} : {$percent} % du budget révisé consommé.",
                route('budget.show', $chantier, absolute: false),
                "budget:{$chantier->id}",
            );
        });

        return $sent;
    }
}
