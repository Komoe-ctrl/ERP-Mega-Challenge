<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Poste occupé par un utilisateur : un titre (« Technicien 1 », « Directeur… »)
 * rattaché à un service. Le compte reste nominatif, le poste n'est qu'un libellé.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property int|null $service_id
 * @property bool $is_default
 */
#[Fillable(['user_id', 'title', 'service_id', 'is_default'])]
class UserPoste extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
