<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateUser
{
    public function execute(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
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
            $user->syncRoles($data->roles);

            return $user;
        });
    }
}
