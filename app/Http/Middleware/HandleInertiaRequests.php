<?php

namespace App\Http\Middleware;

use App\Domain\Administration\Actions\ChoosePoste;
use App\Domain\Administration\Actions\ClockIn;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Support\ModuleNavigation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames()->values() ?? [],
                // Poste affiché dans l'en-tête : titre et service du compte connecté.
                'poste' => self::poste($request->user(), $request),
                // Postes du compte (sélecteur de l'en-tête quand il y en a plusieurs) et poste actif.
                'postes' => self::postes($request->user(), $request),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'navigation' => ModuleNavigation::for($request->user()),
            // Nombre de notifications non lues (pastille du menu), évalué à la demande.
            'unreadNotifications' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
            // Panneau de la cloche : dernières notifications non lues (mêmes données que la page Notifications).
            'recentNotifications' => fn (): array => $request->user() === null ? [] : $request->user()
                ->unreadNotifications()
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn ($n): array => [
                    'id' => $n->id,
                    'title' => data_get($n->data, 'title', ''),
                    'body' => data_get($n->data, 'body', ''),
                    'href' => data_get($n->data, 'href', '/notifications'),
                ])
                ->values()
                ->all(),
            // Sélecteur de profil de la démo : uniquement en environnement local.
            'demo' => app()->isLocal() ? [
                'current' => $request->user()?->getRoleNames()->first(),
                'roles' => array_map(
                    fn (RoleName $role): array => ['value' => $role->value, 'label' => $role->label()],
                    RoleName::cases(),
                ),
            ] : null,
            // Pointage du jour de l'utilisateur authentifié (bouton du tableau de bord, mention de l'en-tête).
            'attendance' => fn () => self::attendance($request->user()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int|null, title: string, service: string}|null
     */
    private static function poste(?User $user, Request $request): ?array
    {
        if ($user === null) {
            return null;
        }

        $active = app(ChoosePoste::class)->active($user, self::chosenPoste($request));

        return ['id' => $active['id'], 'title' => $active['title'], 'service' => $active['service']];
    }

    /**
     * @return list<array{id: int|null, title: string, service: string}>
     */
    private static function postes(?User $user, Request $request): array
    {
        return $user === null ? [] : app(ChoosePoste::class)->postesOf($user);
    }

    private static function chosenPoste(Request $request): ?int
    {
        $id = $request->session()->get(ChoosePoste::SESSION_KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * @return array{today: string, arrivedAt: string|null}|null
     */
    private static function attendance(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $attendance = app(ClockIn::class)->today($user);

        return ['today' => now()->toDateString(), 'arrivedAt' => $attendance?->arrived_at->toIso8601String()];
    }
}
