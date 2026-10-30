<?php

declare(strict_types=1);

namespace App\Domain\Achats\Data;

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\Consultation;
use App\Domain\Achats\Models\ConsultationQuote;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseOrderLine;
use App\Domain\Achats\Models\PurchaseRequestLine;
use App\Domain\Achats\Models\Reception;
use App\Domain\Achats\Models\ReceptionLine;
use App\Domain\Shared\Support\Present;

/**
 * Consultations et bons de commande tels que les affichent les écrans
 * (types TypeScript Consultation et PurchaseOrder).
 */
final class PurchasingData
{
    /**
     * @return array<string, mixed>
     */
    public static function consultation(Consultation $c): array
    {
        $c->loadMissing(['request.lines.article', 'chantier', 'quotes.supplier']);

        return [
            'id' => $c->id,
            'number' => $c->number,
            'requestId' => $c->purchase_request_id,
            'requestNumber' => data_get($c, 'request.number', ''),
            'chantierName' => data_get($c, 'chantier.name', ''),
            'status' => $c->status,
            'deadline' => $c->deadline->toDateString(),
            'note' => $c->note,
            'items' => $c->demand()->lines->map(fn (PurchaseRequestLine $l): string => $l->item()->label.' ('.self::number($l->effectiveQuantity()).')')->implode(', '),
            'minSuppliers' => $c->min_suppliers,
            'quotes' => $c->quotes->map(fn (ConsultationQuote $q): array => [
                'supplierId' => $q->supplier_id,
                'supplierName' => data_get($q, 'supplier.name', ''),
                'unitTotal' => $q->total_amount,
                'delayDays' => $q->delay_days,
                'selected' => $q->is_selected,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(PurchaseOrder $o): array
    {
        $o->loadMissing(['supplier', 'chantier', 'lines.article', 'lines.requestLine.request']);

        return [
            'id' => $o->id,
            'number' => $o->number,
            'supplierId' => $o->supplier_id,
            'supplierName' => data_get($o, 'supplier.name', ''),
            'chantierName' => data_get($o, 'chantier.name', ''),
            'requestNumbers' => $o->lines->map(fn (PurchaseOrderLine $l): string => Present::text($l, 'requestLine.request.number'))->unique()->values()->all(),
            'date' => $o->ordered_on->toDateString(),
            'amount' => $o->amount,
            'status' => $o->status,
            'lines' => $o->lines->map(fn (PurchaseOrderLine $l): array => [
                'id' => $l->id,
                'label' => $l->article instanceof Article ? $l->article->label : '',
                'unit' => $l->article instanceof Article ? $l->article->unit : '',
                'ordered' => (float) $l->quantity,
                'received' => (float) $l->received_quantity,
                'unitPrice' => $l->unit_price,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reception(Reception $r): array
    {
        $r->loadMissing(['order.supplier', 'lines.orderLine.article']);

        return [
            'id' => $r->id,
            'number' => $r->number,
            'orderId' => $r->purchase_order_id,
            'orderNumber' => data_get($r, 'order.number', ''),
            'supplierId' => data_get($r, 'order.supplier_id'),
            'supplierName' => data_get($r, 'order.supplier.name', ''),
            'date' => $r->received_on->toDateString(),
            'gap' => $r->gap,
            'note' => $r->note,
            'lines' => $r->lines->map(fn (ReceptionLine $l): array => [
                'label' => Present::text($l, 'orderLine.article.label'),
                'unit' => Present::text($l, 'orderLine.article.unit'),
                'ordered' => Present::float($l, 'orderLine.quantity'),
                'received' => (float) $l->accepted_quantity,
                'delivered' => (float) $l->delivered_quantity,
                'returned' => (float) $l->returned_quantity,
            ])->values()->all(),
        ];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }
}
