<?php

declare(strict_types=1);

namespace App\Domain\Achats\Data;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\Models\PurchaseRequestEvent;
use App\Domain\Achats\Models\PurchaseRequestLine;

/**
 * Demande d'achat telle que l'affichent les écrans (type TypeScript PurchaseRequest).
 * Les montants sont calculés ici : prix figés après validation, prix du référentiel avant.
 */
final class PurchaseRequestData
{
    /**
     * @return array<string, mixed>
     */
    public static function from(PurchaseRequest $request, bool $detailed = false): array
    {
        $data = [
            'id' => $request->id,
            'number' => $request->number,
            'chantierId' => $request->chantier_id,
            'chantierName' => data_get($request, 'chantier.name', ''),
            'requester' => data_get($request, 'requester.name', '—'),
            'requesterId' => $request->requester_id,
            'createdAt' => $request->created_at?->toDateString() ?? '',
            'status' => $request->status->getValue(),
            'justification' => $request->justification ?? '',
            'needsDirection' => $request->needs_direction,
            'budgetLineId' => $request->budget_line_id,
            'total' => $request->computeTotal(),
        ];

        if (! $detailed) {
            return $data;
        }

        $validated = $request->status->getValue() === 'validee';

        return $data + [
            'lines' => $request->lines->map(fn (PurchaseRequestLine $l): array => [
                'id' => $l->id,
                'articleId' => $l->article_id,
                'articleLabel' => $l->item()->label,
                'unit' => $l->item()->unit,
                'unitPrice' => $l->unitPrice(),
                'quantity' => (float) $l->quantity,
                'adjustedQuantity' => $l->adjusted_quantity !== null ? (float) $l->adjusted_quantity : null,
                'needDate' => $l->need_date->toDateString(),
                'urgency' => $l->urgency,
            ])->values()->all(),
            'frozen' => $validated,
            'history' => $request->events->map(fn (PurchaseRequestEvent $e): array => [
                'date' => $e->created_at?->format('Y-m-d H:i') ?? '',
                'actor' => data_get($e, 'user.name', 'Système'),
                'action' => $e->action,
                'reason' => $e->reason,
            ])->values()->all(),
        ];
    }
}
