import { describe, expect, it } from 'vitest';
import {
    budgetLevel,
    budgetLines,
    budgetOf,
    budgetTotals,
    requestTotal,
    requests,
} from '@/mocks/achats';
import { consumptionTone } from '@/lib/status';

describe('budget d’un chantier (M10)', () => {
    it('reste à engager = budget révisé − engagements − coût réel', () => {
        const t = budgetTotals(budgetOf(1));

        expect(t.remaining).toBe(t.revised - t.committed - t.actual);
    });

    it('les totaux égalent la somme des postes (CA10)', () => {
        const lines = budgetOf(1);
        const t = budgetTotals(lines);

        expect(t.committed).toBe(lines.reduce((s, l) => s + l.committed, 0));
        expect(t.actual).toBe(lines.reduce((s, l) => s + l.actual, 0));
        expect(t.revised).toBe(lines.reduce((s, l) => s + l.revised, 0));
    });

    it('le taux de consommation compte l’engagé et le réel, sans double compte (RG10.1)', () => {
        const t = budgetTotals([
            {
                id: 1,
                chantierId: 1,
                post: 'P',
                nature: 'materiaux',
                initial: 100,
                revised: 100,
                committed: 30,
                actual: 50,
            },
        ]);

        expect(t.consumption).toBe(80);
    });

    it('avertissement à 80 % et alerte critique à 100 % (RG10.3)', () => {
        expect(budgetLevel(79.9)).toBe('ok');
        expect(budgetLevel(80)).toBe('warning');
        expect(budgetLevel(99.9)).toBe('warning');
        expect(budgetLevel(100)).toBe('critical');
        expect(budgetLevel(120)).toBe('critical');
    });

    it('associe un ton de couleur à chaque niveau', () => {
        expect(consumptionTone(50)).toBe('success');
        expect(consumptionTone(85)).toBe('warning');
        expect(consumptionTone(100)).toBe('danger');
    });

    it('données fictives cohérentes : un chantier sous contrôle, un en avertissement, un en alerte critique', () => {
        const level = (id: number) =>
            budgetLevel(budgetTotals(budgetOf(id)).consumption);

        expect(level(1)).toBe('warning');
        expect(level(2)).toBe('critical');
        expect(level(3)).toBe('ok');
        expect(budgetLines.length).toBeGreaterThan(10);
    });
});

describe('montant d’une demande d’achat (M8)', () => {
    it('valorise chaque ligne au prix du référentiel', () => {
        const draft = requests.find((r) => r.status === 'en_attente');

        expect(draft).toBeDefined();
        expect(requestTotal(draft!)).toBeGreaterThan(0);
    });

    it('RG8.6 : une demande validée utilise ses prix figés', () => {
        const validated = requests.find(
            (r) => r.status === 'validee' && r.frozenPrices,
        );

        expect(validated).toBeDefined();
        const frozen = {
            ...validated!,
            frozenPrices: Object.fromEntries(
                Object.keys(validated!.frozenPrices!).map((k) => [k, 1]),
            ),
        };

        // Avec des prix figés à 1 FCFA, le total vaut la somme des quantités.
        expect(requestTotal(frozen)).toBe(
            validated!.lines.reduce(
                (s, l) => s + (l.adjustedQuantity ?? l.quantity),
                0,
            ),
        );
    });

    it('RG8.5 : la quantité ajustée par le valideur remplace la quantité demandée', () => {
        const base = requests.find((r) => r.status === 'en_attente')!;
        const adjusted = {
            ...base,
            lines: base.lines.map((l) => ({ ...l, adjustedQuantity: 0 })),
        };

        expect(requestTotal(adjusted)).toBe(0);
    });
});
