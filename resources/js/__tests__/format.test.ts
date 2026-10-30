import { describe, expect, it } from 'vitest';
import {
    addDays,
    dateFr,
    dateTimeFr,
    daysBetween,
    fcfa,
    fcfaShort,
    number,
    percent,
} from '@/lib/format';

// Les espaces de milliers sont des espaces fines insécables : on normalise pour comparer.
const plain = (s: string) => s.replace(/[\u202f\u00a0]/g, ' ');

describe('montants en FCFA', () => {
    it('formate un montant avec séparateur de milliers et unité', () => {
        expect(plain(fcfa(12_500_000))).toBe('12 500 000 FCFA');
    });

    it('arrondit à l’entier (pas de décimales en FCFA)', () => {
        expect(plain(fcfa(1234.6))).toBe('1 235 FCFA');
    });

    it('gère zéro et les montants négatifs', () => {
        expect(fcfa(0)).toBe('0 FCFA');
        expect(plain(fcfa(-8_500_000))).toBe('-8 500 000 FCFA');
    });

    it('compacte les grands montants pour les cartes KPI', () => {
        expect(plain(fcfaShort(12_500_000))).toBe('12,5 M FCFA');
        expect(plain(fcfaShort(1_250_000_000))).toBe('1,25 Md FCFA');
        expect(plain(fcfaShort(450_000))).toBe('450 000 FCFA');
    });
});

describe('nombres et pourcentages', () => {
    it('utilise la virgule décimale française', () => {
        expect(number(1234.5, 1)).toContain(',5');
        expect(plain(number(1234.5, 1))).toBe('1 234,5');
    });

    it('formate un pourcentage', () => {
        expect(percent(80)).toBe('80 %');
        expect(percent(80.94, 1)).toBe('80,9 %');
    });
});

describe('dates JJ/MM/AAAA', () => {
    it('convertit une date ISO sans décalage de fuseau', () => {
        expect(dateFr('2026-10-29')).toBe('29/10/2026');
        expect(dateFr('2026-01-01')).toBe('01/01/2026');
    });

    it('affiche un tiret pour une date absente', () => {
        expect(dateFr(null)).toBe('—');
        expect(dateFr(undefined)).toBe('—');
    });

    it('ajoute l’heure à une date-heure', () => {
        expect(dateTimeFr('2026-10-29T14:05:00')).toBe('29/10/2026 14:05');
    });

    it('calcule un écart en jours, y compris à travers un changement de mois', () => {
        expect(daysBetween('2026-10-01', '2026-10-08')).toBe(7);
        expect(daysBetween('2026-10-29', '2026-11-02')).toBe(4);
        expect(daysBetween('2026-11-02', '2026-10-29')).toBe(-4);
    });

    it('ajoute des jours à une date', () => {
        expect(addDays('2026-10-29', 3)).toBe('2026-11-01');
        expect(addDays('2026-12-30', 3)).toBe('2027-01-02');
    });
});
