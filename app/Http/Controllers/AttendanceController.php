<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Administration\Actions\ClockIn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pointage d'arrivée de l'utilisateur authentifié (bouton du tableau de bord).
 */
final class AttendanceController extends Controller
{
    public function store(Request $request, ClockIn $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $attendance = $action->execute($user);

        $this->toast('Arrivée pointée à '.$attendance->arrived_at->format('H \h i').'.');

        return back();
    }
}
