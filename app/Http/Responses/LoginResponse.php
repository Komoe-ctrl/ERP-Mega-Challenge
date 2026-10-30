<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Administration\Actions\ClockIn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Après la connexion (et après le défi de double authentification), chaque
 * utilisateur arrive sur la page de son rôle, quelle que soit l'URL tentée
 * avant la connexion : on n'atterrit jamais sur les paramètres de sécurité.
 * À la première connexion de la journée, un rappel invite à pointer l'arrivée.
 */
final class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        if ($user instanceof User && app(ClockIn::class)->today($user) === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'N’oubliez pas de pointer votre arrivée (bouton du tableau de bord).']);
        }

        return redirect()->to(RoleHome::for($user));
    }
}
