<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\CreatePurchaseOrder;
use App\Domain\Achats\Actions\Purchasing;
use App\Domain\Achats\Data\PurchasingData;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\Models\Reception;
use App\Domain\Achats\Models\SupplierInvoice;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Shared\Support\Present;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\StorePurchaseOrderRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bons de commande (RG8.8) : liste, émission directe sous le seuil, fiche.
 */
final class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $user = $this->user($request);
        $status = $request->string('status')->toString();

        $orders = PurchaseOrder::query()
            ->whereHas('chantier', fn ($q) => $q->visibleTo($user))
            ->with(['supplier', 'chantier', 'lines.article', 'lines.requestLine.request'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('number')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $o): array => PurchasingData::order($o));

        $canCreate = $user->can('create', PurchaseOrder::class);

        return Inertia::render('Achats/Commandes/Index', [
            'orders' => $orders,
            'filters' => ['status' => $status],
            'orderable' => $canCreate
                ? PurchaseRequest::query()->where('status', 'validee')->with(['chantier', 'lines.article'])->orderBy('number')->get()
                    ->filter(fn (PurchaseRequest $r): bool => $r->lines->contains(fn ($l): bool => Purchasing::orderable($l) > 0.0))
                    ->map(fn (PurchaseRequest $r): array => ['id' => $r->id, 'number' => $r->number, 'chantierName' => $r->site()->name, 'total' => $r->computeTotal()])->values()
                : [],
            'suppliers' => $canCreate
                ? Supplier::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Supplier $s): array => ['value' => (string) $s->id, 'label' => $s->name])->values()
                : [],
            'threshold' => Purchasing::consultationThreshold(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): RedirectResponse
    {
        $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));

        $lines = [];

        if ($request->filled('purchase_request_id')) {
            $purchaseRequest = PurchaseRequest::query()->with('lines.article')->findOrFail($request->integer('purchase_request_id'));

            foreach ($purchaseRequest->lines as $line) {
                if (Purchasing::orderable($line) > 0.0) {
                    $lines[] = ['request_line_id' => $line->id, 'quantity' => Purchasing::orderable($line)];
                }
            }
        } else {
            foreach ((array) $request->input('lines', []) as $line) {
                $lines[] = ['request_line_id' => Present::int($line, 'request_line_id'), 'quantity' => Present::float($line, 'quantity')];
            }
        }

        $order = $action->direct($supplier, $lines, $this->user($request));
        $this->toast('Bon de commande émis.');

        return to_route('achats.commandes.show', $order);
    }

    public function show(Request $request, PurchaseOrder $order): Response
    {
        $this->authorize('view', $order);

        return Inertia::render('Achats/Commandes/Show', [
            'order' => PurchasingData::order($order),
            'canReceive' => $this->user($request)->can('receive', $order),
            'receptions' => $order->receptions()->with(['order.supplier', 'lines.orderLine.article'])->orderBy('id')->get()
                ->map(fn (Reception $r): array => PurchasingData::reception($r))->values(),
            'invoices' => SupplierInvoice::query()->where('purchase_order_id', $order->id)->orderBy('id')->get()
                ->map(fn (SupplierInvoice $i): array => ['id' => $i->id, 'number' => $i->number, 'dueDate' => $i->due_date->toDateString(), 'amount' => $i->amount, 'status' => $i->status])->values(),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
