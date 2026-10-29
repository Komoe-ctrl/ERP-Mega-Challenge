<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

/**
 * Les contrôleurs restent fins : validation (FormRequest), autorisation
 * (Policy) puis appel d'une classe Action qui porte la logique métier.
 */
abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Message affiché en notification (toast) au prochain rendu de page.
     *
     * @param  'success'|'info'|'warning'|'error'  $type
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
