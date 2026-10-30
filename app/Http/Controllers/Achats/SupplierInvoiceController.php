<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\ManageSupplierInvoice;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\Reception;
use App\Domain\Achats\Models\SupplierInvoice;
use App\Domain\Shared\Support\Present;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\StoreSupplierInvoiceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Factures fournisseurs et contrôle à trois voies (RG8.10).
 */
final class SupplierInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SupplierInvoice::class);
        $user = $this->user($request);
        $status = $request->string('status')->toString();

        $invoices = SupplierInvoice::query()
            ->whereHas('order.chantier', fn ($q) => $q->visibleTo($user))
            ->with(['supplier', 'order', 'reception'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupplierInvoice $i): array => [
                'id' => $i->id,
                'number' => $i->number,
                'supplierName' => data_get($i, 'supplier.name', ''),
                'orderNumber' => data_get($i, 'order.number', ''),
                'receptionNumber' => data_get($i, 'reception.number', ''),
                'date' => $i->invoice_date->toDateString(),
                'dueDate' => $i->due_date->toDateString(),
                'amount' => $i->amount,
                'match' => ['order' => $i->match_order, 'reception' => $i->match_reception, 'price' => $i->match_price],
                'status' => $i->status,
                'canControl' => $user->can('control', $i),
                'canPay' => $user->can('pay', $i),
            ]);

        return Inertia::render('Achats/Factures/Index', [
            'invoices' => $invoices,
            'filters' => ['status' => $status],
            'canCreate' => $user->can('create', SupplierInvoice::class),
            'orders' => $user->can('create', SupplierInvoice::class)
                ? PurchaseOrder::query()->whereIn('status', [PurchaseOrder::PARTIAL, PurchaseOrder::RECEIVED, PurchaseOrder::INVOICED])->with(['supplier', 'receptions'])->orderBy('number')->get()
                    ->map(fn (PurchaseOrder $o): array => [
                        'value' => (string) $o->id,
                        'label' => $o->number.' — '.Present::text($o, 'supplier.name'),
                        'receptions' => $o->receptions->map(fn (Reception $r): array => ['value' => (string) $r->id, 'label' => $r->number])->values()->all(),
                    ])->values()
                : [],
        ]);
    }

    public function store(StoreSupplierInvoiceRequest $request, ManageSupplierInvoice $action): RedirectResponse
    {
        $action->record($request->invoiceData(), $this->user($request));
        $this->toast('Facture enregistrée, à contrôler.');

        return back();
    }

    public function control(Request $request, SupplierInvoice $invoice, ManageSupplierInvoice $action): RedirectResponse
    {
        $this->authorize('control', $invoice);
        $result = $action->control($invoice, $this->user($request));
        $this->toast($result->status === SupplierInvoice::CONTROLLED ? 'Facture contrôlée : elle est payable.' : 'Écart de contrôle : la facture ne concorde pas.', $result->status === SupplierInvoice::CONTROLLED ? 'success' : 'warning');

        return back();
    }

    public function pay(Request $request, SupplierInvoice $invoice, ManageSupplierInvoice $action): RedirectResponse
    {
        $this->authorize('pay', $invoice);
        $action->pay($invoice, $this->user($request), now()->toDateString());
        $this->toast('Facture payée.');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
