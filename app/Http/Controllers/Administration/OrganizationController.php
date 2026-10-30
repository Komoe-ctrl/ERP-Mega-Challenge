<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Actions\CreateOrganizationUnit;
use App\Domain\Administration\Actions\SetOrganizationUnitActive;
use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SaveOrganizationUnitRequest;
use App\Http\Requests\ReasonRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organisation : agences, services et sites (RG1.1). Jamais de suppression,
 * seulement une désactivation motivée.
 */
final class OrganizationController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Agency::class);

        /** @var Collection<int, string> $agencyNames */
        $agencyNames = Agency::query()->pluck('name', 'id');

        return Inertia::render('Administration/Organisation', [
            'agencies' => Agency::query()->orderBy('code')->get()->map(fn (Agency $a): array => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'city' => $a->city ?? '',
                'active' => $a->is_active,
            ])->values(),
            'services' => Service::query()->withCount('users')->orderBy('id')->get()->map(fn (Service $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'users' => (int) ($s->users_count ?? 0),
                'active' => $s->is_active,
            ])->values(),
            'sites' => Site::query()->orderBy('id')->get()->map(fn (Site $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'agency' => $agencyNames->get($s->agency_id, ''),
                'agencyId' => $s->agency_id,
                'type' => $s->type,
                'active' => $s->is_active,
            ])->values(),
        ]);
    }

    public function storeAgency(SaveOrganizationUnitRequest $request, CreateOrganizationUnit $action): RedirectResponse
    {
        /** @var array{code: string, name: string, city: string} $data */
        $data = $request->safe()->only(['code', 'name', 'city']);
        $action->agency($data);
        $this->toast('Agence créée.');

        return back();
    }

    public function storeService(SaveOrganizationUnitRequest $request, CreateOrganizationUnit $action): RedirectResponse
    {
        /** @var array{code: string, name: string} $data */
        $data = $request->safe()->only(['code', 'name']);
        $action->service($data);
        $this->toast('Service créé.');

        return back();
    }

    public function storeSite(SaveOrganizationUnitRequest $request, CreateOrganizationUnit $action): RedirectResponse
    {
        /** @var array{code: string, name: string, agency_id: int, type: string} $data */
        $data = $request->safe()->only(['code', 'name', 'agency_id', 'type']);
        $action->site($data);
        $this->toast('Site créé.');

        return back();
    }

    public function deactivateAgency(ReasonRequest $request, Agency $agency, SetOrganizationUnitActive $action): RedirectResponse
    {
        return $this->setActive($request, $agency, false, $action);
    }

    public function reactivateAgency(Agency $agency, SetOrganizationUnitActive $action): RedirectResponse
    {
        return $this->setActive(null, $agency, true, $action);
    }

    public function deactivateService(ReasonRequest $request, Service $service, SetOrganizationUnitActive $action): RedirectResponse
    {
        return $this->setActive($request, $service, false, $action);
    }

    public function reactivateService(Service $service, SetOrganizationUnitActive $action): RedirectResponse
    {
        return $this->setActive(null, $service, true, $action);
    }

    public function deactivateSite(ReasonRequest $request, Site $site, SetOrganizationUnitActive $action): RedirectResponse
    {
        return $this->setActive($request, $site, false, $action);
    }

    public function reactivateSite(Site $site, SetOrganizationUnitActive $action): RedirectResponse
    {
        return $this->setActive(null, $site, true, $action);
    }

    private function setActive(?ReasonRequest $request, Agency|Service|Site $unit, bool $active, SetOrganizationUnitActive $action): RedirectResponse
    {
        $this->authorize('update', $unit);
        $action->execute($unit, $active, $request?->string('reason')->toString());
        $this->toast($active ? 'Élément réactivé.' : 'Élément désactivé.');

        return back();
    }
}
