<?php

declare(strict_types=1);

namespace App\Http\Controllers\Situations;

use App\Domain\Devis\Models\QuoteWork;
use App\Domain\Marches\Models\Contract;
use App\Domain\Shared\Support\Present;
use App\Domain\Situations\Actions\DecideSituation;
use App\Domain\Situations\Actions\SaveSituation;
use App\Domain\Situations\Models\Situation;
use App\Domain\Situations\Models\SituationPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Situations de travaux (M11) : calcul du net à payer (RG11.1, RG11.2), validation qui fige (RG11.3)
 * et paiements du client.
 */
final class SituationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Situation::class);
        $q = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $situations = Situation::query()->with(['contract.client'])
            ->when($q !== '', fn ($query) => $query->whereHas('contract', fn ($c) => $c->where('number', 'like', "%{$q}%")
                ->orWhereHas('client', fn ($cl) => $cl->where('name', 'like', "%{$q}%"))))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Situation $s): array => $this->row($s));

        return Inertia::render('Situations/Index', [
            'situations' => $situations,
            'filters' => ['q' => $q, 'status' => $status],
            'contracts' => Contract::query()->with('client')->orderByDesc('id')->get()
                ->map(fn (Contract $c): array => ['id' => $c->id, 'label' => "{$c->number} — ".Present::text($c, 'client.name')])->values(),
            'can' => ['create' => $request->user()?->can('create', Situation::class) ?? false],
        ]);
    }

    public function store(Request $request, SaveSituation $action): RedirectResponse
    {
        $this->authorize('create', Situation::class);
        $data = $request->validate([
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'situation_date' => ['required', 'date_format:Y-m-d'],
            'progress' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $contract = Contract::query()->findOrFail((int) $data['contract_id']);
        $situation = $action->create($contract, $data, $request->user() ?? abort(401));
        $this->toast("Situation n°{$situation->number} créée en brouillon : les taux viennent du marché (RG6.3).");

        return to_route('situations.show', $situation);
    }

    public function show(Request $request, Situation $situation): Response
    {
        $this->authorize('view', $situation);
        $situation->load(['contract.client', 'contract.quote', 'lines', 'payments']);
        $user = $request->user();
        $contract = Contract::query()->findOrFail($situation->contract_id);
        $calc = $situation->computation();
        $lineProgress = $situation->lines->pluck('progress', 'quote_work_id');

        return Inertia::render('Situations/Show', [
            'situation' => $this->row($situation) + [
                'contractId' => $contract->id,
                'previousProgress' => (float) $situation->previous_progress,
                'marketAmount' => $situation->market_amount,
                'retentionRate' => (float) $situation->retention_rate,
                'advanceRefundRate' => (float) $situation->advance_refund_rate,
                'vatRate' => (float) $situation->vat_rate,
                'calc' => $calc,
                'paid' => $situation->paidAmount(),
                'remaining' => $situation->status === Situation::DRAFT ? $calc['netTtc'] : $calc['netTtc'] - $situation->paidAmount(),
            ],
            // RG11.1 : avancement par ouvrage du devis du marché.
            'works' => QuoteWork::query()->where('quote_id', (int) $contract->quote_id)->orderBy('position')->orderBy('id')->get()
                ->map(fn (QuoteWork $w): array => [
                    'id' => $w->id,
                    'label' => $w->label,
                    'total' => $w->total(),
                    'progress' => (float) (is_numeric($lineProgress[$w->id] ?? null) ? $lineProgress[$w->id] : $situation->progress_cumul),
                ])->values(),
            'payments' => $situation->payments->map(fn (SituationPayment $p): array => [
                'id' => $p->id,
                'date' => $p->paid_at->toDateString(),
                'amount' => $p->amount,
                'reference' => $p->reference ?? '',
            ])->values(),
            'can' => [
                'update' => $user?->can('update', $situation) ?? false,
                'validate' => $user?->can('validate', $situation) ?? false,
                'pay' => $user?->can('pay', $situation) ?? false,
            ],
        ]);
    }

    public function update(Request $request, Situation $situation, SaveSituation $action): RedirectResponse
    {
        $this->authorize('update', $situation);
        $data = $request->validate([
            'situation_date' => ['required', 'date_format:Y-m-d'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'works' => ['nullable', 'array'],
            'works.*' => ['numeric', 'min:0', 'max:100'],
            'retention_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'advance_refund_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $action->update($situation, $data);
        $this->toast('Situation enregistrée.');

        return back();
    }

    public function validateSituation(Request $request, Situation $situation, DecideSituation $action): RedirectResponse
    {
        $this->authorize('validate', $situation);
        $action->validate($situation, $request->user() ?? abort(401));
        $this->toast("Situation n°{$situation->number} validée et figée : une correction passera par la suivante.");

        return back();
    }

    public function storePayment(Request $request, Situation $situation, DecideSituation $action): RedirectResponse
    {
        $this->authorize('pay', $situation);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $action->recordPayment($situation, (int) $data['amount'], $data['paid_at'], $data['reference'] ?? null, $request->user() ?? abort(401));
        $this->toast('Paiement enregistré.');

        return back();
    }

    /** @return array<string, mixed> */
    private function row(Situation $situation): array
    {
        $calc = $situation->computation();

        return [
            'id' => $situation->id,
            'number' => $situation->number,
            'contractNumber' => Present::text($situation, 'contract.number'),
            'clientName' => Present::text($situation, 'contract.client.name'),
            'date' => $situation->situation_date->toDateString(),
            'status' => $situation->status,
            'progressCumul' => (float) $situation->progress_cumul,
            'amount' => $calc['amount'],
            'netTtc' => $calc['netTtc'],
        ];
    }
}
