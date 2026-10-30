<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\ManageConsultation;
use App\Domain\Achats\Actions\Purchasing;
use App\Domain\Achats\Data\PurchasingData;
use App\Domain\Achats\Models\Consultation;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\AddQuoteRequest;
use App\Http\Requests\Achats\AwardConsultationRequest;
use App\Http\Requests\Achats\OpenConsultationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consultations de fournisseurs, comparatif et attribution (RG8.11).
 */
final class ConsultationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Consultation::class);
        $user = $this->user($request);
        $status = $request->string('status')->toString();

        $consultations = Consultation::query()
            ->whereHas('chantier', fn ($q) => $q->visibleTo($user))
            ->with(['request.lines.article', 'chantier', 'quotes.supplier'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('number')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Consultation $c): array => PurchasingData::consultation($c));

        return Inertia::render('Achats/Consultations/Index', [
            'consultations' => $consultations,
            'filters' => ['status' => $status],
            'consultable' => $user->can('create', Consultation::class)
                ? PurchaseRequest::query()->where('status', 'validee')->whereDoesntHave('consultation')->with(['chantier', 'lines.article'])->orderBy('number')->get()
                    ->filter(fn (PurchaseRequest $r): bool => $r->lines->contains(fn ($l): bool => Purchasing::orderable($l) > 0.0))
                    ->map(fn (PurchaseRequest $r): array => ['id' => $r->id, 'number' => $r->number, 'chantierName' => $r->site()->name, 'total' => $r->computeTotal()])->values()
                : [],
            'threshold' => Purchasing::consultationThreshold(),
            'minSuppliers' => Purchasing::minimumSuppliers(),
        ]);
    }

    public function store(OpenConsultationRequest $request, ManageConsultation $action): RedirectResponse
    {
        $purchaseRequest = PurchaseRequest::query()->findOrFail($request->integer('purchase_request_id'));
        $consultation = $action->open($purchaseRequest, $request->string('deadline')->toString(), $request->filled('note') ? $request->string('note')->toString() : null, $this->user($request));
        $this->toast('Consultation ouverte.');

        return to_route('achats.consultations.show', $consultation);
    }

    public function show(Request $request, Consultation $consultation): Response
    {
        $this->authorize('view', $consultation);
        $user = $this->user($request);

        $submitted = $consultation->quotes()->pluck('supplier_id')->all();

        return Inertia::render('Achats/Consultations/Show', [
            'consultation' => PurchasingData::consultation($consultation),
            // RG3.1 : seuls les fournisseurs actifs de la bonne catégorie sont proposés.
            'suppliers' => Purchasing::eligibleSuppliers($consultation->demand())
                ->reject(fn ($s): bool => in_array($s->id, $submitted, true))
                ->map(fn ($s): array => ['value' => (string) $s->id, 'label' => $s->name])->values(),
            'canManage' => $user->can('update', $consultation),
            'orderId' => $consultation->status === Consultation::AWARDED
                ? PurchaseOrder::query()->where('consultation_id', $consultation->id)->value('id')
                : null,
        ]);
    }

    public function addQuote(AddQuoteRequest $request, Consultation $consultation, ManageConsultation $action): RedirectResponse
    {
        $action->addQuote($consultation, $request->integer('supplier_id'), $request->integer('total_amount'), $request->integer('delay_days'));
        $this->toast('Offre enregistrée.');

        return back();
    }

    public function award(AwardConsultationRequest $request, Consultation $consultation, ManageConsultation $action): RedirectResponse
    {
        $order = $action->award($consultation, $request->integer('supplier_id'), $this->user($request));
        $this->toast('Fournisseur retenu : le bon de commande est créé.');

        return to_route('achats.commandes.show', $order);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
