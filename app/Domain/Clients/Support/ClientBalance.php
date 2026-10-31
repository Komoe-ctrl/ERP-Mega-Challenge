<?php

declare(strict_types=1);

namespace App\Domain\Clients\Support;

use App\Domain\Clients\Models\Client;
use App\Domain\Marches\Models\Contract;
use App\Domain\Projets\Models\Project;
use App\Domain\Situations\Models\Situation;

/**
 * Agrégats de la fiche client (RG2.2) : projets, marchés, situations facturées, paiements reçus et solde
 * restant dû. Le facturé est le net à payer TTC des situations validées ou payées ; l'encaissé, la somme
 * de leurs paiements.
 */
final class ClientBalance
{
    /**
     * @return array{projectsInProgress: int, projectsTotal: int, contracted: int, invoiced: int, paid: int, due: int}
     */
    public function for(Client $client): array
    {
        $projects = Project::query()->where('client_id', $client->id)->get();

        $contracts = Contract::query()->with('amendments')->where('client_id', $client->id)->get();
        $contracted = $contracts->reduce(fn (int $sum, Contract $c): int => $sum + $c->totals()['updatedAmount'], 0);

        $situations = Situation::query()->with('payments')
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->whereIn('status', [Situation::VALIDATED, Situation::PAID])
            ->get();
        $invoiced = $situations->reduce(fn (int $sum, Situation $s): int => $sum + $s->computation()['netTtc'], 0);
        $paid = $situations->reduce(fn (int $sum, Situation $s): int => $sum + $s->paidAmount(), 0);

        return [
            'projectsInProgress' => $projects->filter(fn (Project $p): bool => $p->status->getValue() === 'en_cours')->count(),
            'projectsTotal' => $projects->count(),
            'contracted' => $contracted,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'due' => $invoiced - $paid,
        ];
    }
}
