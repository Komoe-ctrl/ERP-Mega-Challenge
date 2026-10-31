<?php

declare(strict_types=1);

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Shared\Support\DocumentNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et modification d'un client (M2). RG2.1 : le client reçoit un numéro unique ; un doublon
 * sur le nom et le contact est signalé à la création et ne passe que si l'utilisateur le confirme.
 */
final class SaveClient
{
    /**
     * @param  array{name: string, type: string, contact?: string|null, phone?: string|null, email?: string|null, city?: string|null, address?: string|null}  $data
     */
    public function create(array $data, bool $confirmDuplicate = false): Client
    {
        if (! $confirmDuplicate && ($duplicate = $this->duplicateOf($data)) instanceof Client) {
            throw ValidationException::withMessages([
                'name' => "Doublon possible : {$duplicate->number} « {$duplicate->name} » a déjà ce nom et ce contact (RG2.1). Confirmez pour créer quand même.",
            ]);
        }

        return DB::transaction(fn (): Client => Client::query()->create($data + [
            'number' => DocumentNumbers::next('client', 'CLI-{YYYY}-{SEQ:4}'),
        ]));
    }

    /**
     * @param  array{name: string, type: string, contact?: string|null, phone?: string|null, email?: string|null, city?: string|null, address?: string|null}  $data
     */
    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }

    /**
     * Client existant de même nom et même contact (comparaison insensible à la casse et aux espaces).
     *
     * @param  array{name: string, contact?: string|null}  $data
     */
    public function duplicateOf(array $data): ?Client
    {
        $name = mb_strtolower(trim($data['name']));
        $contact = mb_strtolower(trim((string) ($data['contact'] ?? '')));

        return Client::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
            ->whereRaw("LOWER(TRIM(COALESCE(contact, ''))) = ?", [$contact])
            ->first();
    }
}
