<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Actions\SaveRole;
use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SaveRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get()
            ->map(fn (Role $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $r->label(),
                'is_profile' => $r->profile() !== null,
                'users_count' => $r->users_count,
                'permissions_count' => $r->permissions_count,
            ]);

        return Inertia::render('Administration/Roles/Index', [
            'roles' => $roles,
            'can' => [
                'create' => $request->user()?->can('create', Role::class) ?? false,
                'update' => $request->user()?->can('update', new Role) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Administration/Roles/Form', [
            'role' => null,
            ...$this->matrix(),
        ]);
    }

    public function store(SaveRoleRequest $request, SaveRole $action): RedirectResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $action->execute($request->toData(), $actor);

        $this->toast('Rôle créé.');

        return to_route('administration.roles.index');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Administration/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label(),
                'is_profile' => $role->profile() !== null,
                'permissions' => $role->permissions->pluck('name')->values(),
            ],
            ...$this->matrix(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRole $action): RedirectResponse
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $action->execute($request->toData(), $actor, $role);

        $this->toast('Rôle mis à jour.');

        return to_route('administration.roles.index');
    }

    /**
     * Grille des droits : modules en lignes, actions en colonnes.
     *
     * @return array<string, mixed>
     */
    private function matrix(): array
    {
        return [
            'actions' => array_map(
                fn (PermissionAction $a): array => ['value' => $a->value, 'label' => $a->label()],
                PermissionAction::cases(),
            ),
            'modules' => array_map(
                fn (Module $m): array => ['value' => $m->value, 'label' => $m->label(), 'version' => $m->version()],
                Module::cases(),
            ),
        ];
    }
}
