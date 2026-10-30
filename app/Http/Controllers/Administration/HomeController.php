<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Data\ActivityEntryData;
use App\Domain\Administration\Models\Activity;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accueil de l'Administration : page d'arrivée de l'Administrateur
 * (comptes, dernières entrées du journal des activités).
 */
final class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Administration/Accueil', [
            'stats' => [
                'users' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'inactive' => User::query()->where('is_active', false)->count(),
            ],
            'recentUsers' => User::query()
                ->with(['roles', 'service'])
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'job_title' => $u->job_title,
                    'is_active' => $u->is_active,
                ])
                ->values(),
            'entries' => Activity::query()
                ->with(['causer', 'subject'])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Activity $a): ActivityEntryData => ActivityEntryData::from($a))
                ->values(),
        ]);
    }
}
