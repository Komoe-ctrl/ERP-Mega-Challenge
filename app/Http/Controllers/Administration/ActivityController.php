<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Data\ActivityEntryData;
use App\Domain\Administration\Models\Activity;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal des activités en lecture seule (RG1.5).
 */
final class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $event = $request->string('event')->toString();
        $actor = $request->integer('actor');

        $entries = Activity::query()
            ->with(['causer', 'subject'])
            ->when($event !== '', fn ($q) => $q->whereIn('event', ActivityEntryData::storedEvents($event)))
            ->when($actor > 0, fn ($q) => $q->where('causer_id', $actor)->where('causer_type', (new User)->getMorphClass()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Activity $a): ActivityEntryData => ActivityEntryData::from($a));

        return Inertia::render('Administration/Journal', [
            'entries' => $entries,
            'filters' => ['event' => $event, 'actor' => $actor > 0 ? (string) $actor : ''],
            'actors' => User::query()
                ->whereIn('id', Activity::query()->whereNotNull('causer_id')->select('causer_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])
                ->values(),
        ]);
    }
}
