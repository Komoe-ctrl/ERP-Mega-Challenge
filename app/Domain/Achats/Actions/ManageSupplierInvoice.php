<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseOrderLine;
use App\Domain\Achats\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Factures fournisseurs (étapes 10 à 12 du processus d'achat).
 *
 * Contrôle à trois voies (RG8.10) : une facture n'est payable que si elle
 * concorde avec le bon de commande, la réception et les prix commandés.
 */
final class ManageSupplierInvoice
{
    /**
     * @param  array{number: string, purchase_order_id: int, reception_id: int|null, invoice_date: string, due_date: string, amount: int}  $data
     */
    public function record(array $data, User $by): SupplierInvoice
    {
        $order = PurchaseOrder::query()->findOrFail($data['purchase_order_id']);

        if (SupplierInvoice::query()->where('supplier_id', $order->supplier_id)->where('number', $data['number'])->exists()) {
            throw ValidationException::withMessages(['number' => 'Cette facture a déjà été saisie pour ce fournisseur.']);
        }

        return SupplierInvoice::query()->create([
            'number' => $data['number'],
            'supplier_id' => $order->supplier_id,
            'purchase_order_id' => $order->id,
            'reception_id' => $data['reception_id'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'status' => SupplierInvoice::TO_CONTROL,
            'created_by' => $by->id,
        ]);
    }

    /**
     * RG8.10 : confronte la facture au bon de commande (montant restant à facturer),
     * à la réception (marchandise reçue) et aux prix commandés (valeur reçue non facturée).
     * Elle devient « contrôlée » (payable) si les trois voies concordent, sinon « écart ».
     */
    public function control(SupplierInvoice $invoice, User $by): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $by): SupplierInvoice {
            $order = PurchaseOrder::query()->with('lines')->lockForUpdate()->findOrFail($invoice->purchase_order_id);

            // Factures déjà acceptées sur ce bon : elles consomment le reste à facturer.
            $invoiced = (int) SupplierInvoice::query()->where('purchase_order_id', $order->id)->whereKeyNot($invoice->id)
                ->whereIn('status', [SupplierInvoice::CONTROLLED, SupplierInvoice::PAID])->sum('amount');

            $receivedValue = (int) $order->lines->sum(fn (PurchaseOrderLine $l): int => (int) round($l->unit_price * (float) $l->received_quantity));
            $receptionOk = $receivedValue > 0
                && ($invoice->reception_id === null || $order->receptions()->whereKey($invoice->reception_id)->exists());

            $match = [
                'match_order' => $invoice->amount <= $order->amount - $invoiced,
                'match_reception' => $receptionOk,
                'match_price' => $invoice->amount <= $receivedValue - $invoiced,
            ];

            $invoice->update($match + [
                'status' => in_array(false, $match, true) ? SupplierInvoice::GAP : SupplierInvoice::CONTROLLED,
                'controlled_by' => $by->id,
                'controlled_at' => now(),
            ]);

            $this->syncOrderStatus($order);

            return $invoice;
        });
    }

    /** RG8.10, RG15.2 : seule une facture contrôlée est payable. */
    public function pay(SupplierInvoice $invoice, User $by, string $paidOn): SupplierInvoice
    {
        if ($invoice->status !== SupplierInvoice::CONTROLLED) {
            throw ValidationException::withMessages(['status' => 'Une facture n\'est payable que si elle concorde avec le bon de commande et la réception (RG8.10).']);
        }

        return DB::transaction(function () use ($invoice, $by, $paidOn): SupplierInvoice {
            $invoice->update(['status' => SupplierInvoice::PAID, 'paid_on' => $paidOn, 'paid_by' => $by->id]);
            $this->syncOrderStatus(PurchaseOrder::query()->with('lines')->findOrFail($invoice->purchase_order_id));

            return $invoice;
        });
    }

    /** Le bon passe à « facturé » quand les factures acceptées couvrent son montant, puis à « payé ». */
    private function syncOrderStatus(PurchaseOrder $order): void
    {
        if (! in_array($order->status, [PurchaseOrder::RECEIVED, PurchaseOrder::INVOICED, PurchaseOrder::PAID], true)) {
            return;
        }

        $accepted = SupplierInvoice::query()->where('purchase_order_id', $order->id)->whereIn('status', [SupplierInvoice::CONTROLLED, SupplierInvoice::PAID]);
        $covered = (int) (clone $accepted)->sum('amount') >= $order->amount;
        $allPaid = $covered && ! (clone $accepted)->where('status', SupplierInvoice::CONTROLLED)->exists();

        $order->update(['status' => $allPaid ? PurchaseOrder::PAID : ($covered ? PurchaseOrder::INVOICED : PurchaseOrder::RECEIVED)]);
    }
}
