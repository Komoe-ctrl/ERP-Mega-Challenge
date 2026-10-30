<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Matrice rôles × permissions (RG1.2), en lecture seule : les droits se
 * modifient depuis l'écran des rôles.
 */
final class MatrixController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Administration/Matrice', [
            'roles' => Role::query()->with('permissions')->orderBy('id')->get()->map(fn (Role $r): array => [
                'name' => $r->name,
                'label' => $r->label(),
                'permissions' => $r->permissions->pluck('name')->values()->all(),
            ])->values(),
            'modules' => array_map(fn (Module $m): array => ['value' => $m->value, 'label' => $m->label()], Module::cases()),
            'actions' => array_map(fn (PermissionAction $a): array => ['value' => $a->value, 'label' => $a->label()], PermissionAction::cases()),
        ]);
    }
}
