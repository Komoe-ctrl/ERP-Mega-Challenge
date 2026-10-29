<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Direction, Finance et Administrateur doivent activer la double
 * authentification (cahier des charges, section 11) : tant qu'elle n'est pas
 * confirmée, ils sont renvoyés vers la page de sécurité.
 */
final class RequireTwoFactorForPrivilegedRoles
{
    /** Pages nécessaires pour activer la double authentification ou se déconnecter. */
    private const ALLOWED = [
        'settings/*',
        'user/*',
        'logout',
        'two-factor-challenge',
        'up',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! $user->mustUseTwoFactor()
            || $user->hasTwoFactorEnabled()
            || $request->is(...self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('security.edit')
            ->with('status', 'Activez la double authentification pour continuer.');
    }
}
