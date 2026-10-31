<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marches;

use App\Domain\Devis\Models\Quote;
use App\Domain\Marches\Actions\CreateContract;
use App\Domain\Marches\Actions\ManageAmendment;
use App\Domain\Marches\Models\Contract;
use App\Domain\Marches\Models\ContractAmendment;
use App\Domain\Shared\Support\Present;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marchés (M6) : création depuis un devis validé (RG6.1), avenants (RG6.2) et conditions de paiement (RG6.3).
 */
final class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);
        $q = trim($request->string('q')->toString());

        $contracts = Contract::query()->with(['client', 'project', 'amendments'])
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('number', 'like', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%{$q}%")),
            ))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Contract $c): array => $this->row($c));

        $signable = Quote::query()->with(['client', 'works'])
            ->where('status', 'valide')
            ->whereNotIn('id', Contract::query()->whereNotNull('quote_id')->select('quote_id'))
            ->orderByDesc('id')->get()
            ->map(fn (Quote $quote): array => [
                'id' => $quote->id,
                'label' => "{$quote->number} — ".Present::text($quote, 'client.name')." ({$quote->totals()['ht']} FCFA HT)",
            ])->values();

        return Inertia::render('Marches/Index', [
            'contracts' => $contracts,
            'filters' => ['q' => $q],
            'signableQuotes' => $signable,
            'can' => ['create' => $request->user()?->can('create', Contract::class) ?? false],
        ]);
    }

    public function store(Request $request, CreateContract $action): RedirectResponse
    {
        $this->authorize('create', Contract::class);
        $data = $request->validate([
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'signed_at' => ['required', 'date_format:Y-m-d'],
            'deadline_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'advance_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'retention_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $quote = Quote::query()->findOrFail((int) $data['quote_id']);
        unset($data['quote_id']);
        $contract = $action->execute($quote, $data, $request->user() ?? abort(401));
        $this->toast("Marché {$contract->number} créé : le montant initial reprend celui du devis (RG6.1).");

        return to_route('marches.show', $contract);
    }

    public function show(Request $request, Contract $contract): Response
    {
        $this->authorize('view', $contract);
        $contract->load(['client', 'project', 'quote', 'amendments']);
        $user = $request->user();

        return Inertia::render('Marches/Show', [
            'contract' => $this->row($contract) + [
                'advanceRate' => (float) $contract->advance_rate,
                'retentionRate' => (float) $contract->retention_rate,
                'paymentDays' => $contract->payment_days,
                'deadlineDays' => $contract->deadline_days,
                'projectId' => $contract->project_id,
                'quoteId' => $contract->quote_id,
                'quoteNumber' => Present::text($contract, 'quote.number'),
                'amendments' => $contract->amendments->map(fn (ContractAmendment $a): array => [
                    'id' => $a->id,
                    'number' => $a->number,
                    'date' => $a->amendment_date->toDateString(),
                    'label' => $a->label,
                    'amount' => $a->amount,
                    'days' => $a->days,
                    'validated' => $a->is_validated,
                    'canValidate' => ! $a->is_validated && $a->created_by !== $user?->id && ($user?->can('validate', $contract) ?? false),
                ])->values(),
            ],
            // Les situations de travaux seront reliées avec le module M11.
            'situations' => [],
            'can' => ['amend' => $user?->can('update', $contract) ?? false],
        ]);
    }

    public function storeAmendment(Request $request, Contract $contract, ManageAmendment $action): RedirectResponse
    {
        $this->authorize('update', $contract);
        $data = $request->validate([
            'label' => ['required', 'string', 'min:5', 'max:200'],
            'amount' => ['required', 'integer'],
            'days' => ['required', 'integer', 'min:-3650', 'max:3650'],
            'amendment_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $action->add($contract, $data, $request->user() ?? abort(401));
        $this->toast('Avenant enregistré, en attente de validation.');

        return back();
    }

    public function validateAmendment(Request $request, Contract $contract, ContractAmendment $amendment, ManageAmendment $action): RedirectResponse
    {
        $this->authorize('validate', $contract);
        $action->validate($contract, $amendment, $request->user() ?? abort(401));
        $this->toast('Avenant validé : le montant actualisé et le délai sont mis à jour.');

        return back();
    }

    /** @return array<string, mixed> */
    private function row(Contract $contract): array
    {
        $totals = $contract->totals();

        return [
            'id' => $contract->id,
            'number' => $contract->number,
            'clientName' => Present::text($contract, 'client.name'),
            'projectName' => Present::text($contract, 'project.name'),
            'signedAt' => $contract->signed_at->toDateString(),
            'initialAmount' => $contract->initial_amount,
            'updated' => $totals['updatedAmount'],
            'amendmentsAmount' => $totals['amendmentsAmount'],
            'days' => $totals['contractualDays'],
        ];
    }
}
