<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Actions\CreateUser;
use App\Domain\Administration\Actions\SetUserActive;
use App\Domain\Administration\Actions\UpdateUser;
use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Role;
use App\Domain\Administration\Models\Service;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with(['roles', 'agency', 'service'])
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'agency' => $u->agency?->name,
                'service' => $u->service?->name,
                'roles' => $u->roles->map(fn ($r): string => $r instanceof Role ? $r->label() : '')->values(),
            ]);

        return Inertia::render('Administration/Users/Index', [
            'users' => $users,
            'filters' => ['q' => $search],
            'can' => [
                'create' => $request->user()?->can('create', User::class) ?? false,
                'update' => $request->user()?->can('update', new User) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Administration/Users/Form', [
            'user' => null,
            ...$this->options(),
        ]);
    }

    public function store(SaveUserRequest $request, CreateUser $action): RedirectResponse
    {
        $action->execute($request->toData());

        $this->toast('Utilisateur créé.');

        return to_route('administration.users.index');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Administration/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'agency_id' => $user->agency_id,
                'service_id' => $user->service_id,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames()->values(),
            ],
            ...$this->options(),
        ]);
    }

    public function update(SaveUserRequest $request, User $user, UpdateUser $action): RedirectResponse
    {
        $action->execute($user, $request->toData());

        $this->toast('Utilisateur mis à jour.');

        return to_route('administration.users.index');
    }

    public function deactivate(User $user, SetUserActive $action): RedirectResponse
    {
        $this->authorize('deactivate', $user);
        $action->execute($user, false);

        $this->toast('Compte désactivé.');

        return back();
    }

    public function reactivate(User $user, SetUserActive $action): RedirectResponse
    {
        $this->authorize('reactivate', $user);
        $action->execute($user, true);

        $this->toast('Compte réactivé.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'agencies' => Agency::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->get()->map(fn (Role $r): array => [
                'name' => $r->name,
                'label' => $r->label(),
            ])->values(),
        ];
    }
}
