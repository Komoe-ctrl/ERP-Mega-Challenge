<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\RecordReception;
use App\Domain\Achats\Data\PurchasingData;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\Reception;
use App\Domain\Fournisseurs\Models\SupplierEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\StoreReceptionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Réceptions de livraisons (RG8.9) : liste, saisie par ligne avec reliquat ou retour.
 */
final class ReceptionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $user = $this->user($request);

        $receptions = Reception::query()
            ->whereHas('order.chantier', fn ($q) => $q->visibleTo($user))
            ->with(['order.supplier', 'lines.orderLine.article'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Reception $r): array => PurchasingData::reception($r) + [
                'evaluated' => SupplierEvaluation::query()->where('reception_id', $r->id)->exists(),
            ]);

        return Inertia::render('Achats/Receptions/Index', [
            'receptions' => $receptions,
            'receivable' => PurchaseOrder::query()->whereIn('status', [PurchaseOrder::ISSUED, PurchaseOrder::PARTIAL])
                ->whereHas('chantier', fn ($q) => $q->visibleTo($user))->with('supplier')->orderBy('number')->get()
                ->filter(fn (PurchaseOrder $o): bool => $user->can('receive', $o))
                ->map(fn (PurchaseOrder $o): array => ['id' => $o->id, 'number' => $o->number, 'supplierName' => data_get($o, 'supplier.name', '')])->values(),
        ]);
    }

    public function create(PurchaseOrder $order): Response
    {
        $this->authorize('receive', $order);

        return Inertia::render('Achats/Receptions/Create', ['order' => PurchasingData::order($order)]);
    }

    public function store(StoreReceptionRequest $request, PurchaseOrder $order, RecordReception $action): RedirectResponse
    {
        $reception = $action->execute($order, $request->receptionLines(), $request->filled('note') ? $request->string('note')->toString() : null, $this->user($request));
        $this->toast("Réception {$reception->number} enregistrée.");

        return to_route('achats.commandes.show', $order);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
