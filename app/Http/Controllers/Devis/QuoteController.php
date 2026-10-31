<?php

declare(strict_types=1);

namespace App\Http\Controllers\Devis;

use App\Domain\Clients\Models\Client;
use App\Domain\Devis\Actions\DecideQuote;
use App\Domain\Devis\Actions\SaveQuote;
use App\Domain\Devis\Models\Ouvrage;
use App\Domain\Devis\Models\Quote;
use App\Domain\Devis\Models\QuoteWork;
use App\Domain\Marches\Models\Contract;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Support\Present;
use App\Http\Controllers\Controller;
use App\Http\Requests\Devis\SaveQuoteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Devis (M5) : liste, création en brouillon, édition des ouvrages (RG5.1), totaux et marge prévue (RG5.2),
 * soumission, validation, refus et nouvelle version (RG5.3).
 */
final class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quote::class);
        $q = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $quotes = Quote::query()->with(['client', 'project', 'works'])
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('number', 'like', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%{$q}%")),
            ))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Quote $quote): array => $this->row($quote));

        return Inertia::render('Devis/Index', [
            'quotes' => $quotes,
            'filters' => ['q' => $q, 'status' => $status],
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->whereIn('status', SaveQuote::QUOTABLE_PROJECT_STATUSES)
                ->orderBy('name')->get(['id', 'name', 'client_id']),
            'can' => ['create' => $request->user()?->can('create', Quote::class) ?? false],
        ]);
    }

    public function store(Request $request, SaveQuote $action): RedirectResponse
    {
        $this->authorize('create', Quote::class);
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $quote = $action->create($data, $request->user() ?? abort(401));
        $this->toast("Devis {$quote->number} créé en brouillon. Ajoutez ses ouvrages.");

        return to_route('devis.show', $quote);
    }

    public function show(Request $request, Quote $quote): Response
    {
        $this->authorize('view', $quote);
        $quote->load(['client', 'project', 'works']);
        $user = $request->user();
        $contract = Contract::query()->where('quote_id', $quote->id)->first();

        return Inertia::render('Devis/Show', [
            'contract' => $contract === null ? null : ['id' => $contract->id, 'number' => $contract->number],
            'quote' => $this->row($quote) + [
                'discountRate' => (float) $quote->discount_rate,
                'vatRate' => (float) $quote->vat_rate,
                'directCost' => $quote->direct_cost,
                'refusalReason' => $quote->refusal_reason,
                'works' => $quote->works->map(fn (QuoteWork $w): array => [
                    'id' => $w->id,
                    'ouvrageId' => $w->ouvrage_id,
                    'label' => $w->label,
                    'unit' => $w->unit,
                    'quantity' => (float) $w->quantity,
                    'unitPrice' => $w->unit_price,
                    'components' => $w->components ?? '',
                ])->values(),
            ],
            'versions' => Quote::query()->where('number', $quote->number)->orderBy('version')->get(['id', 'version', 'status'])
                ->map(fn (Quote $v): array => ['id' => $v->id, 'version' => $v->version, 'status' => $v->status->getValue()])->values(),
            'library' => Ouvrage::query()->with('components.article')->where('is_active', true)->orderBy('label')->get()
                ->map(fn (Ouvrage $o): array => [
                    'id' => $o->id,
                    'label' => $o->label,
                    'unit' => $o->unit,
                    'cost' => $o->cost(),
                    'price' => $o->price(),
                ])->values(),
            'can' => [
                'update' => $user?->can('update', $quote) ?? false,
                'validate' => $user?->can('validate', $quote) ?? false,
                'duplicate' => $quote->status->getValue() === 'refuse' && ($user?->can('create', Quote::class) ?? false),
                'createContract' => $contract === null && $quote->status->getValue() === 'valide' && ($user?->can('create', Contract::class) ?? false),
                'submit' => $quote->status->getValue() === 'brouillon' && ($user?->can('update', $quote) ?? false),
            ],
        ]);
    }

    public function update(SaveQuoteRequest $request, Quote $quote, SaveQuote $action): RedirectResponse
    {
        $this->authorize('update', $quote);
        /** @var array{discount_rate: float|int|string, vat_rate: float|int|string, direct_cost: int, works: list<array{label: string, unit: string, quantity: float|int|string, unit_price: int, components?: string|null, ouvrage_id?: int|null}>} $data */
        $data = $request->validated();
        $action->update($quote, $data);
        $this->toast('Devis enregistré.');

        return back();
    }

    public function addOuvrage(Request $request, Quote $quote, SaveQuote $action): RedirectResponse
    {
        $this->authorize('update', $quote);
        $data = $request->validate([
            'ouvrage_id' => ['required', 'integer', 'exists:ouvrages,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ]);

        $ouvrage = Ouvrage::query()->findOrFail((int) $data['ouvrage_id']);
        $action->addOuvrage($quote, $ouvrage, (float) $data['quantity']);
        $this->toast("Ouvrage ajouté : prix de vente {$ouvrage->price()} FCFA / {$ouvrage->unit}, calculé depuis sa composition (RG5.1).");

        return back();
    }

    public function submit(Quote $quote, DecideQuote $action): RedirectResponse
    {
        $this->authorize('update', $quote);
        $action->submit($quote);
        $this->toast('Devis soumis pour validation.');

        return back();
    }

    public function validateQuote(Request $request, Quote $quote, DecideQuote $action): RedirectResponse
    {
        $this->authorize('validate', $quote);
        $action->validate($quote, $request->user() ?? abort(401));
        $this->toast('Devis validé : il peut alimenter le marché.');

        return back();
    }

    public function refuse(Request $request, Quote $quote, DecideQuote $action): RedirectResponse
    {
        $this->authorize('validate', $quote);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $action->refuse($quote, $request->user() ?? abort(401), $data['reason']);
        $this->toast('Devis refusé. Il peut être dupliqué en nouvelle version.');

        return back();
    }

    public function newVersion(Request $request, Quote $quote, DecideQuote $action): RedirectResponse
    {
        $this->authorize('create', Quote::class);
        $copy = $action->newVersion($quote, $request->user() ?? abort(401));
        $this->toast("Version v{$copy->version} créée en brouillon ; la version v{$quote->version} est conservée (RG5.3).");

        return to_route('devis.show', $copy);
    }

    /** @return array<string, mixed> */
    private function row(Quote $quote): array
    {
        $totals = $quote->totals();

        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'version' => $quote->version,
            'clientName' => Present::text($quote, 'client.name'),
            'projectName' => Present::text($quote, 'project.name'),
            'date' => $quote->quote_date->toDateString(),
            'status' => $quote->status->getValue(),
            'ht' => $totals['ht'],
            'marginRate' => $totals['marginRate'],
        ];
    }
}
