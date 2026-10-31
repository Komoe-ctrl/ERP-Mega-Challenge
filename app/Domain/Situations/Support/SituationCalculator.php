<?php

declare(strict_types=1);

namespace App\Domain\Situations\Support;

/**
 * Calcul d'une situation de travaux.
 *
 * Net à payer n = (Cumul n − Cumul n−1) − Retenue de garantie − Remboursement d'acompte + TVA.
 * RG11.1 : le cumul vient de l'avancement ; RG11.2 : retenue et remboursement s'appliquent au montant
 * de la situation, aux taux du marché ; la TVA s'applique au net hors taxes.
 */
final class SituationCalculator
{
    /**
     * @return array{cumul: int, previousCumul: int, amount: int, retention: int, advanceRefund: int, netHt: int, vat: int, netTtc: int}
     */
    public static function compute(int $marketAmount, float $progress, float $previousProgress, float $retentionRate, float $advanceRefundRate, float $vatRate): array
    {
        $cumul = (int) round($marketAmount * $progress / 100);
        $previousCumul = (int) round($marketAmount * $previousProgress / 100);
        $amount = $cumul - $previousCumul;
        $retention = (int) round($amount * $retentionRate / 100);
        $advanceRefund = (int) round($amount * $advanceRefundRate / 100);
        $netHt = $amount - $retention - $advanceRefund;
        $vat = (int) round($netHt * $vatRate / 100);

        return [
            'cumul' => $cumul,
            'previousCumul' => $previousCumul,
            'amount' => $amount,
            'retention' => $retention,
            'advanceRefund' => $advanceRefund,
            'netHt' => $netHt,
            'vat' => $vat,
            'netTtc' => $netHt + $vat,
        ];
    }
}
