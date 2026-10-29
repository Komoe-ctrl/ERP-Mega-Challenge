<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateUser
{
    public function execute(UserData $data): User
    {
        if ($data->password === null) {
            throw new InvalidArgumentException('Le mot de passe initial est obligatoire.');
        }

        return DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'agency_id' => $data->agencyId,
                'service_id' => $data->serviceId,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ]);
            // Compte créé par un administrateur : l'adresse est considérée comme vérifiée.
            $user->email_verified_at = now();
            $user->save();

            $user->syncRoles($data->roles);

            return $user;
        });
    }
}
