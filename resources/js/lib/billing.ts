import type { Contract, QuoteDoc, Situation, WorkItem } from '@/types/erp';

/* ---------- Devis (M5) ---------- */

export const workTotal = (w: WorkItem): number =>
    Math.round(w.quantity * w.unitPrice);

/** Totaux d'un devis. RG5.2 : marge prévue = total HT - déboursé sec. */
export function quoteTotals(
    q: Pick<QuoteDoc, 'works' | 'discountRate' | 'vatRate' | 'directCost'>,
) {
    const subtotal = q.works.reduce((sum, w) => sum + workTotal(w), 0);
    const discount = Math.round((subtotal * q.discountRate) / 100);
    const ht = subtotal - discount;
    const vat = Math.round((ht * q.vatRate) / 100);
    const ttc = ht + vat;
    const margin = ht - q.directCost;
    const marginRate = ht === 0 ? 0 : (margin / ht) * 100;

    return { subtotal, discount, ht, vat, ttc, margin, marginRate };
}

/* ---------- Marchés (M6) ---------- */

/** RG6.2 : montant actualisé = montant initial + somme des avenants validés. */
export function contractTotals(c: Contract) {
    const validated = c.amendments.filter((a) => a.validated);
    const amendmentsAmount = validated.reduce((s, a) => s + a.amount, 0);
    const extraDays = validated.reduce((s, a) => s + a.days, 0);

    return {
        updatedAmount: c.initialAmount + amendmentsAmount,
        amendmentsAmount,
        contractualDays: c.deadlineDays + extraDays,
    };
}

/* ---------- Situations de travaux (M11) ---------- */

/**
 * Net à payer n = (Cumul n - Cumul n-1) - Retenue de garantie n
 *                 - Remboursement d'acompte n + Taxes n.
 * RG11.1 : le cumul vient de l'avancement ; RG11.2 : retenue et remboursement
 * s'appliquent au montant de la situation, aux taux du marché. Les taxes
 * (TVA) s'appliquent au net hors taxes.
 */
export function computeSituation(
    s: Pick<
        Situation,
        | 'marketAmount'
        | 'progressCumul'
        | 'previousProgress'
        | 'retentionRate'
        | 'advanceRefundRate'
        | 'vatRate'
    >,
) {
    const cumul = Math.round((s.marketAmount * s.progressCumul) / 100);
    const previousCumul = Math.round(
        (s.marketAmount * s.previousProgress) / 100,
    );
    const amount = cumul - previousCumul;
    const retention = Math.round((amount * s.retentionRate) / 100);
    const advanceRefund = Math.round((amount * s.advanceRefundRate) / 100);
    const netHt = amount - retention - advanceRefund;
    const vat = Math.round((netHt * s.vatRate) / 100);

    return {
        cumul,
        previousCumul,
        amount,
        retention,
        advanceRefund,
        netHt,
        vat,
        netTtc: netHt + vat,
    };
}
