<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Domain\Administration\Support\AccessChangeAudit;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateUser
{
    public function __construct(private readonly AccessChangeAudit $audit) {}

    public function execute(User $user, UserData $data, User $actor): User
    {
        $before = $user->getRoleNames()->map(fn (mixed $name): string => is_string($name) ? $name : '')->values()->all();
        $changesRoles = $this->sorted($before) !== $this->sorted($data->roles);

        // Défense en profondeur : un administrateur ne peut pas modifier ses propres rôles.
        if ($changesRoles && $actor->is($user)) {
            throw new DomainException('Vous ne pouvez pas modifier vos propres rôles.');
        }

        return DB::transaction(function () use ($user, $data, $actor, $before, $changesRoles): User {
            $user->fill([
                'name' => $data->name,
                'email' => $data->email,
                'agency_id' => $data->agencyId,
                'service_id' => $data->serviceId,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ]);

            // Mot de passe laissé vide = inchangé.
            if ($data->password !== null && $data->password !== '') {
                $user->password = $data->password;
            }

            $user->save();

            if ($changesRoles) {
                $user->syncRoles($data->roles);
                $this->audit->userRolesChanged($actor, $user, $before, $data->roles);
            }

            return $user;
        });
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
