<?php

declare(strict_types=1);

namespace App\Domain\Clients\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact d'un client (la fiche en compte plusieurs).
 *
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $email
 */
#[Fillable(['client_id', 'name', 'role', 'phone', 'email'])]
class ClientContact extends Model
{
    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
