/**
 * Formatage des montants, quantités et dates de l'ERP.
 * Montants : « 12 500 000 FCFA » (entier, sans décimales). Dates : JJ/MM/AAAA.
 */

/** Date du jour des données fictives (fixe pour que la démo reste cohérente). */
export const TODAY = '2026-10-29';

function groupThousands(digits: string): string {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

export function number(value: number, decimals = 0): string {
    const [int, dec] = Math.abs(value).toFixed(decimals).split('.');
    const sign = value < 0 ? '-' : '';

    return sign + groupThousands(int) + (dec ? ',' + dec : '');
}

/** 12500000 -> « 12 500 000 FCFA » */
export function fcfa(amount: number): string {
    return `${number(Math.round(amount))} FCFA`;
}

/** Version compacte pour les cartes KPI : « 12,5 M FCFA ». */
export function fcfaShort(amount: number): string {
    const abs = Math.abs(amount);

    if (abs >= 1_000_000_000) {
        return `${number(amount / 1_000_000_000, 2)} Md FCFA`;
    }

    if (abs >= 1_000_000) {
        return `${number(amount / 1_000_000, 1)} M FCFA`;
    }

    return fcfa(amount);
}

export function percent(value: number, decimals = 0): string {
    return `${number(value, decimals)} %`;
}

/** '2026-10-29' -> '29/10/2026' (sans passer par Date : pas de décalage de fuseau). */
export function dateFr(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const [y, m, d] = iso.slice(0, 10).split('-');

    return `${d}/${m}/${y}`;
}

/** '2026-10-29T14:05:00' -> '29/10/2026 14:05' */
export function dateTimeFr(iso: string): string {
    return `${dateFr(iso)} ${iso.slice(11, 16)}`;
}

/** Nombre de jours entre deux dates ISO (b - a). */
export function daysBetween(a: string, b: string): number {
    const toUtc = (s: string) => {
        const [y, m, d] = s.slice(0, 10).split('-').map(Number);

        return Date.UTC(y, m - 1, d);
    };

    return Math.round((toUtc(b) - toUtc(a)) / 86_400_000);
}

export function addDays(iso: string, days: number): string {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d + days));

    return date.toISOString().slice(0, 10);
}
