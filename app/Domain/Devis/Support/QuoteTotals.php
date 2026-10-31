<?php

declare(strict_types=1);

namespace App\Domain\Devis\Support;

use App\Domain\Devis\Models\Quote;
use App\Domain\Devis\Models\QuoteWork;

/**
 * Totaux d'un devis. RG5.2 : marge prévue = total HT − déboursé sec.
 */
final class QuoteTotals
{
    /** @return array{subtotal: int, discount: int, ht: int, vat: int, ttc: int, margin: int, marginRate: float} */
    public static function of(Quote $quote): array
    {
        $subtotal = (int) $quote->works->sum(fn (QuoteWork $w): int => $w->total());
        $discount = (int) round($subtotal * (float) $quote->discount_rate / 100);
        $ht = $subtotal - $discount;
        $vat = (int) round($ht * (float) $quote->vat_rate / 100);
        $margin = $ht - $quote->direct_cost;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'ht' => $ht,
            'vat' => $vat,
            'ttc' => $ht + $vat,
            'margin' => $margin,
            'marginRate' => $ht === 0 ? 0.0 : round($margin / $ht * 100, 2),
        ];
    }
}
