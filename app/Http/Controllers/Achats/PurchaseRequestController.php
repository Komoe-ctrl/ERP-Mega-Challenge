<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\CancelPurchaseRequest;
use App\Domain\Achats\Actions\DecidePurchaseRequest;
use App\Domain\Achats\Actions\RequestRouting;
use App\Domain\Achats\Actions\SavePurchaseRequest;
use App\Domain\Achats\Data\PurchaseRequestData;
use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\Models\Reception;
use App\Domain\Achats\Models\SupplierInvoice;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Support\BudgetSummary;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Support\Present;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\SavePurchaseRequestRequest;
use App\Http\Requests\Achats\ValidatePurchaseRequestRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demandes d'achat (M8.1) : saisie, validation motivée, annulation. Contrôleur
 * fin : toutes les règles (RG8.1 à RG8.7) sont dans les Actions.
 */
final class PurchaseRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $user = $this->user($request);

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
            'chantier' => $request->string('chantier')->toString(),
        ];

        $requests = $this->visibleTo($user)
            ->with(['chantier', 'requester', 'lines.article'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('number', 'like', "%{$filters['q']}%")->orWhere('justification', 'like', "%{$filters['q']}%"),
            ))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['chantier'] !== '', fn ($q) => $q->where('chantier_id', (int) $filters['chantier']))
            ->orderByDesc('number')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseRequest $r): array => PurchaseRequestData::from($r));

        return Inertia::render('Achats/Demandes/Index', [
            'requests' => $requests,
            'filters' => $filters,
            'chantiers' => Chantier::query()->visibleTo($user)->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Chantier $c): array => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'pending' => $user->can('achats.validate') ? $this->decidable($user)->count() : 0,
        ]);
    }

    /**
     * Suivi « workflow » des demandes d'achat : l'historique de chaque demande (qui, quand, quelle
     * décision, quel motif), au même format que celui des pièces communes.
     */
    public function workflow(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $requests = $this->visibleTo($this->user($request))
            ->with(['events.user'])
            ->orderByDesc('number')
            ->limit(100)
            ->get();

        $statuses = [
            'brouillon' => ['saisie', 'Brouillon'],
            'en_attente' => ['en_validation', 'En attente de validation'],
            'validee' => ['approuvee', 'Validée'],
            'refusee' => ['rejetee', 'Refusée'],
            'renvoyee' => ['rejetee', 'Renvoyée pour correction'],
            'annulee' => ['rejetee', 'Annulée'],
        ];

        return Inertia::render('Pieces/Suivi', [
            'kind' => ['value' => 'da', 'label' => 'Demande d\'achat'],
            'steps' => [],
            'pieces' => $requests->map(function (PurchaseRequest $r) use ($statuses): array {
                $status = $r->status->getValue();

                return [
                    'id' => $r->id,
                    'number' => $r->number,
                    'title' => $r->justification ?? '',
                    'status' => $statuses[$status][0] ?? 'saisie',
                    'status_label' => $statuses[$status][1] ?? $status,
                    'current_step' => null,
                    'total' => $r->total_amount,
                    'events' => $r->events->map(fn ($e): array => [
                        'at' => $e->created_at?->format('Y-m-d H:i') ?? '',
                        'by' => $e->user instanceof User ? $e->user->name : 'Système',
                        'action' => $e->action,
                        'step' => null,
                        'comment' => $e->reason,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ]);
    }

    public function pending(Request $request, RequestRouting $routing): Response
    {
        abort_unless($this->user($request)->can('achats.validate'), 403);
        $user = $this->user($request);

        return Inertia::render('Achats/Demandes/AValider', [
            'pending' => $this->decidable($user)->map(function (PurchaseRequest $r) use ($routing): array {
                $remaining = $this->summary($r->chantier_id)->remaining();

                return PurchaseRequestData::from($r) + [
                    'remaining' => $remaining,
                    'direction' => $routing->needsDirection($r->requestedTotal(), $r->computeTotal(), $remaining),
                ];
            })->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseRequest::class);

        return Inertia::render('Achats/Demandes/Create', [
            'request' => null,
            'presetChantier' => $request->integer('chantier') ?: null,
            ...$this->formOptions($this->user($request)),
        ]);
    }

    public function store(SavePurchaseRequestRequest $request, SavePurchaseRequest $action): RedirectResponse
    {
        $created = $action->create($request->requestData(), $this->user($request), $request->shouldSubmit());
        $this->toast($request->shouldSubmit() ? 'Demande soumise au valideur.' : 'Brouillon enregistré.');

        return to_route('achats.demandes.show', $created);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest, RequestRouting $routing): Response
    {
        $this->authorize('view', $purchaseRequest);
        $user = $this->user($request);
        $purchaseRequest->load(['chantier', 'requester', 'lines.article', 'events.user']);

        $summary = $this->summary($purchaseRequest->chantier_id);
        $directionRequired = $purchaseRequest->status->getValue() === 'en_attente'
            && $routing->needsDirection($purchaseRequest->requestedTotal(), $purchaseRequest->computeTotal(), $summary->remaining());
        $canDecide = $user->can('decide', $purchaseRequest);

        return Inertia::render('Achats/Demandes/Show', [
            'request' => PurchaseRequestData::from($purchaseRequest, detailed: true),
            'budget' => $summary->toArray(),
            'threshold' => $routing->threshold(),
            'isAuthor' => $purchaseRequest->requester_id === $user->id,
            'canDecide' => $canDecide,
            'directionRequired' => $directionRequired,
            // Une demande à valider par la Direction ne peut pas être validée par un autre profil (RG8.7).
            'canValidate' => $canDecide && (! $directionRequired || $user->hasRole(RoleName::DirectionGenerale->value)),
            'canCancel' => $user->can('cancel', $purchaseRequest),
            'canEdit' => $user->can('update', $purchaseRequest),
            // CA8.2 : depuis la demande, ses bons de commande, réceptions, factures et leur état de paiement.
            'purchasing' => $this->purchasingTrail($purchaseRequest),
        ]);
    }

    public function edit(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        $this->authorize('update', $purchaseRequest);
        $purchaseRequest->load(['chantier', 'requester', 'lines.article', 'events.user']);

        return Inertia::render('Achats/Demandes/Create', [
            'request' => PurchaseRequestData::from($purchaseRequest, detailed: true),
            'presetChantier' => null,
            ...$this->formOptions($this->user($request)),
        ]);
    }

    public function update(SavePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, SavePurchaseRequest $action): RedirectResponse
    {
        $action->update($purchaseRequest, $request->requestData(), $this->user($request), $request->shouldSubmit());
        $this->toast($request->shouldSubmit() ? 'Demande soumise au valideur.' : 'Brouillon enregistré.');

        return to_route('achats.demandes.show', $purchaseRequest);
    }

    public function validateRequest(ValidatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest, DecidePurchaseRequest $action): RedirectResponse
    {
        $action->validate($purchaseRequest, $this->user($request), $request->adjustments());
        $this->toast('Demande validée : prix figés et montant engagé sur le budget du chantier.');

        return back();
    }

    public function refuse(ReasonRequest $request, PurchaseRequest $purchaseRequest, DecidePurchaseRequest $action): RedirectResponse
    {
        $this->authorize('decide', $purchaseRequest);
        $action->refuse($purchaseRequest, $this->user($request), $request->string('reason')->toString());
        $this->toast('Demande refusée.');

        return back();
    }

    public function sendBack(ReasonRequest $request, PurchaseRequest $purchaseRequest, DecidePurchaseRequest $action): RedirectResponse
    {
        $this->authorize('decide', $purchaseRequest);
        $action->sendBack($purchaseRequest, $this->user($request), $request->string('reason')->toString());
        $this->toast('Demande renvoyée pour correction.');

        return back();
    }

    public function cancel(ReasonRequest $request, PurchaseRequest $purchaseRequest, CancelPurchaseRequest $action): RedirectResponse
    {
        $this->authorize('cancel', $purchaseRequest);
        $action->execute($purchaseRequest, $this->user($request), $request->string('reason')->toString());
        $this->toast('Demande annulée.');

        return back();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function purchasingTrail(PurchaseRequest $purchaseRequest): array
    {
        $orders = PurchaseOrder::query()
            ->whereHas('lines.requestLine', fn ($q) => $q->where('purchase_request_id', $purchaseRequest->id))
            ->with('supplier')->orderBy('number')->get();

        return [
            'orders' => $orders->map(fn (PurchaseOrder $o): array => ['id' => $o->id, 'number' => $o->number, 'status' => $o->status, 'supplierName' => Present::text($o, 'supplier.name')])->values()->all(),
            'receptions' => Reception::query()->whereIn('purchase_order_id', $orders->modelKeys())->orderBy('id')->get()
                ->map(fn (Reception $r): array => ['id' => $r->id, 'number' => $r->number, 'gap' => $r->gap])->values()->all(),
            'invoices' => SupplierInvoice::query()->whereIn('purchase_order_id', $orders->modelKeys())->orderBy('id')->get()
                ->map(fn (SupplierInvoice $i): array => ['id' => $i->id, 'number' => $i->number, 'status' => $i->status])->values()->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    /**
     * Demandes des chantiers que l'utilisateur peut voir (RG7.2).
     *
     * @return Builder<PurchaseRequest>
     */
    private function visibleTo(User $user): Builder
    {
        return PurchaseRequest::query()->whereHas('chantier', fn ($q) => $q->visibleTo($user));
    }

    /**
     * Demandes en attente que l'utilisateur peut décider.
     *
     * @return Collection<int, PurchaseRequest>
     */
    private function decidable(User $user): Collection
    {
        return $this->visibleTo($user)
            ->where('status', 'en_attente')
            ->with(['chantier', 'requester', 'lines.article'])
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseRequest $r): bool => $user->can('decide', $r))
            ->values();
    }

    private function summary(int $chantierId): BudgetSummary
    {
        return BudgetSummary::fromLines(BudgetLine::query()->where('chantier_id', $chantierId)->get());
    }

    /**
     * Options du formulaire : chantiers ouverts, budget disponible par chantier
     * (RG8.3), articles actifs avec leur prix de référence et seuil de validation.
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $chantiers = Chantier::query()->visibleTo($user)->with('project')->orderBy('code')->get()
            ->reject(fn (Chantier $c): bool => $c->isReadOnly())->values();

        $budgets = [];
        foreach ($chantiers as $c) {
            $lines = BudgetLine::query()->where('chantier_id', $c->id)->orderBy('id')->get();
            $budgets[$c->id] = BudgetSummary::fromLines($lines)->toArray() + [
                'lines' => $lines->map(fn (BudgetLine $l): array => [
                    'id' => $l->id,
                    'post' => $l->post,
                    'nature' => $l->nature,
                    'remaining' => $l->revised_amount - $l->committed_amount - $l->actual_amount,
                ])->values()->all(),
            ];
        }

        return [
            'chantiers' => $chantiers->map(fn (Chantier $c): array => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name])->values(),
            'budgets' => (object) $budgets,
            'articles' => Article::query()->where('is_active', true)->orderBy('label')->get()
                ->map(fn (Article $a): array => ['id' => $a->id, 'label' => $a->label, 'category' => $a->category, 'unit' => $a->unit, 'price' => $a->price])->values(),
            'categories' => Article::CATEGORIES,
            'threshold' => app(RequestRouting::class)->threshold(),
        ];
    }
}
