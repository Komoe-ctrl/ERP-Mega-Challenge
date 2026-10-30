<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Après la connexion (et après le défi de double authentification), chaque
 * utilisateur arrive sur la page de son rôle, quelle que soit l'URL tentée
 * avant la connexion : on n'atterrit jamais sur les paramètres de sécurité.
 */
final class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $home = RoleHome::for($request->user());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->to($home);
    }
}
