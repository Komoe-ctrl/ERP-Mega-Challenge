<?php

declare(strict_types=1);

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientContact;

/**
 * Contacts d'un client : ajout et suppression.
 */
final class ManageClientContact
{
    /**
     * @param  array{name: string, role?: string|null, phone?: string|null, email?: string|null}  $data
     */
    public function add(Client $client, array $data): ClientContact
    {
        return $client->contacts()->create($data);
    }

    public function remove(ClientContact $contact): void
    {
        $contact->delete();
    }
}
