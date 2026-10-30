<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Administration\Actions\ChoosePoste;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sélecteur de poste de l'en-tête, pour un utilisateur qui occupe plusieurs postes.
 */
final class PosteController extends Controller
{
    public function update(Request $request, ChoosePoste $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate(['poste_id' => ['required', 'integer']]);

        abort_unless($action->choose($user, (int) $validated['poste_id']), 403, 'Ce poste n’appartient pas à votre compte.');

        $this->toast('Poste changé.');

        return back();
    }
}
