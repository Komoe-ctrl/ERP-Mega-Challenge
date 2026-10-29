import { describe, expect, it } from 'vitest';
import {
    computeSituation,
    contractTotals,
    quoteTotals,
    workTotal,
} from '@/lib/billing';
import { ca11Example } from '@/mocks/v3';
import type { Contract } from '@/types/erp';

describe('situations de travaux (M11)', () => {
    it('CA11 : marché de 100 M, 30 % -> 40 %, retenue 5 %, acompte 10 % -> net à payer HT 8 500 000 FCFA', () => {
        const s = computeSituation(ca11Example);

        expect(s.amount).toBe(10_000_000);
        expect(s.retention).toBe(500_000);
        expect(s.advanceRefund).toBe(1_000_000);
        expect(s.netHt).toBe(8_500_000);
    });

    it('applique la TVA sur le net hors taxes', () => {
        const s = computeSituation({ ...ca11Example, vatRate: 18 });

        expect(s.vat).toBe(1_530_000);
        expect(s.netTtc).toBe(10_030_000);
    });

    it('calcule le cumul à partir de l’avancement et du montant du marché (RG11.1)', () => {
        const s = computeSituation(ca11Example);

        expect(s.cumul).toBe(40_000_000);
        expect(s.previousCumul).toBe(30_000_000);
    });

    it('une première situation n’a pas de cumul précédent', () => {
        const s = computeSituation({
            ...ca11Example,
            previousProgress: 0,
            progressCumul: 12,
        });

        expect(s.amount).toBe(12_000_000);
    });
});

describe('devis (M5)', () => {
    const quote = {
        works: [
            {
                id: 1,
                label: 'A',
                unit: 'm²',
                quantity: 100,
                unitPrice: 10_000,
                components: '',
            },
            {
                id: 2,
                label: 'B',
                unit: 'm³',
                quantity: 10,
                unitPrice: 50_000,
                components: '',
            },
        ],
        discountRate: 10,
        vatRate: 18,
        directCost: 1_000_000,
    };

    it('totalise un ouvrage : quantité x prix unitaire', () => {
        expect(workTotal(quote.works[0])).toBe(1_000_000);
    });

    it('calcule sous-total, remise, HT, TVA et TTC', () => {
        const t = quoteTotals(quote);

        expect(t.subtotal).toBe(1_500_000);
        expect(t.discount).toBe(150_000);
        expect(t.ht).toBe(1_350_000);
        expect(t.vat).toBe(243_000);
        expect(t.ttc).toBe(1_593_000);
    });

    it('RG5.2 : marge prévue = total HT - déboursé sec', () => {
        const t = quoteTotals(quote);

        expect(t.margin).toBe(350_000);
        expect(Math.round(t.marginRate * 10) / 10).toBe(25.9);
    });
});

describe('marchés (M6)', () => {
    const contract: Contract = {
        id: 1,
        number: 'MAR-1',
        clientName: 'X',
        projectName: 'Y',
        initialAmount: 100_000_000,
        signedAt: '2026-01-01',
        deadlineDays: 300,
        advanceRate: 10,
        retentionRate: 5,
        paymentDays: 30,
        amendments: [
            {
                number: 'AV-1',
                date: '2026-02-01',
                label: 'a',
                amount: 8_000_000,
                days: 10,
                validated: true,
            },
            {
                number: 'AV-2',
                date: '2026-03-01',
                label: 'b',
                amount: 12_000_000,
                days: 20,
                validated: false,
            },
        ],
    };

    it('RG6.2 : montant actualisé = initial + avenants validés (les avenants en attente sont ignorés)', () => {
        const t = contractTotals(contract);

        expect(t.updatedAmount).toBe(108_000_000);
        expect(t.amendmentsAmount).toBe(8_000_000);
    });

    it('un avenant validé prolonge aussi le délai contractuel', () => {
        expect(contractTotals(contract).contractualDays).toBe(310);
    });
});
