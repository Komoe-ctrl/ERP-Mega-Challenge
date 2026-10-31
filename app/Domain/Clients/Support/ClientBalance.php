<?php

declare(strict_types=1);

namespace App\Domain\Clients\Support;

use App\Domain\Clients\Models\Client;
use App\Domain\Projets\Models\Project;

/**
 * Agrégats de la fiche client (RG2.2) : projets, marchés, situations facturées, paiements reçus et solde
 * restant dû. Les montants facturés, encaissés et dus se calculent à partir des situations de travaux et
 * des paiements (M11) ; tant que ces tables n'existent pas, ils valent zéro.
 */
final class ClientBalance
{
    /**
     * @return array{projectsInProgress: int, projectsTotal: int, contracted: int, invoiced: int, paid: int, due: int}
     */
    public function for(Client $client): array
    {
        $projects = Project::query()->where('client_id', $client->id)->get();

        return [
            'projectsInProgress' => $projects->filter(fn (Project $p): bool => $p->status->getValue() === 'en_cours')->count(),
            'projectsTotal' => $projects->count(),
            'contracted' => 0,
            'invoiced' => 0,
            'paid' => 0,
            'due' => 0,
        ];
    }
}
