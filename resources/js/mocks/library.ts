import { articleById } from '@/mocks/achats';

/**
 * Bibliothèque d'ouvrages (RG5.1) : un ouvrage regroupe des articles du
 * référentiel et de la main-d'œuvre ; son prix se calcule à partir de sa
 * composition.
 */
export type OuvrageComponent = {
    articleId?: number;
    label?: string;
    quantity: number;
    unitPrice?: number;
};

export type Ouvrage = {
    id: number;
    label: string;
    unit: string;
    components: OuvrageComponent[];
};

export const ouvrages: Ouvrage[] = [
    {
        id: 1,
        label: 'Béton armé dosé à 350 kg/m³',
        unit: 'm³',
        components: [
            { articleId: 1, quantity: 7 }, // sacs de ciment
            { articleId: 9, quantity: 0.45 }, // sable
            { articleId: 11, quantity: 0.85 }, // gravier
            { articleId: 5, quantity: 11 }, // fer HA 12
            {
                label: 'Coffrage et main-d’œuvre',
                quantity: 1,
                unitPrice: 62_000,
            },
        ],
    },
    {
        id: 2,
        label: 'Maçonnerie de parpaings de 15',
        unit: 'm²',
        components: [
            { articleId: 25, quantity: 12.5 },
            { articleId: 1, quantity: 0.35 },
            { articleId: 9, quantity: 0.03 },
            { label: 'Main-d’œuvre', quantity: 1, unitPrice: 4_800 },
        ],
    },
    {
        id: 3,
        label: 'Peinture intérieure deux couches',
        unit: 'm²',
        components: [
            { articleId: 15, quantity: 0.05 },
            {
                label: 'Enduit, sous-couche et main-d’œuvre',
                quantity: 1,
                unitPrice: 1_900,
            },
        ],
    },
    {
        id: 4,
        label: 'Plomberie — évacuation PVC Ø100',
        unit: 'ml',
        components: [
            { articleId: 17, quantity: 0.25 },
            { articleId: 18, quantity: 0.3 },
            { articleId: 19, quantity: 0.02 },
            { label: 'Pose', quantity: 1, unitPrice: 2_400 },
        ],
    },
];

export function componentCost(c: OuvrageComponent): number {
    if (c.articleId) {
        return Math.round((articleById(c.articleId)?.price ?? 0) * c.quantity);
    }

    return Math.round((c.unitPrice ?? 0) * c.quantity);
}

/** Déboursé sec unitaire d'un ouvrage = somme de ses composants. */
export const ouvrageCost = (o: Ouvrage): number =>
    o.components.reduce((s, c) => s + componentCost(c), 0);

/** Prix de vente unitaire = déboursé sec + frais généraux et marge (coefficient de vente). */
export const SALE_COEFFICIENT = 1.25;
export const ouvragePrice = (o: Ouvrage): number =>
    Math.round((ouvrageCost(o) * SALE_COEFFICIENT) / 100) * 100;

export function ouvrageComposition(o: Ouvrage): string {
    return o.components
        .map((c) =>
            c.articleId
                ? (articleById(c.articleId)?.label.split(' — ')[0] ?? '')
                : (c.label ?? ''),
        )
        .join(', ');
}

/** Contacts, documents et paiements d'un client (fiche client, RG2.2). */
export const clientExtras: Record<
    number,
    {
        contacts: {
            name: string;
            role: string;
            phone: string;
            email: string;
        }[];
        documents: { name: string; date: string }[];
        payments: { date: string; label: string; amount: number }[];
    }
> = {
    1: {
        contacts: [
            {
                name: 'Fanta Ouattara',
                role: 'Directrice des opérations',
                phone: '+225 27 22 48 10 30',
                email: 'f.ouattara@immoivoire.example',
            },
            {
                name: 'Serge Aka',
                role: 'Responsable financier',
                phone: '+225 07 07 45 66 12',
                email: 's.aka@immoivoire.example',
            },
        ],
        documents: [
            { name: 'Contrat de marché signé.pdf', date: '2026-05-20' },
            {
                name: 'Attestation de garantie bancaire.pdf',
                date: '2026-05-22',
            },
        ],
        payments: [
            {
                date: '2026-10-26',
                label: 'Règlement situation n°2',
                amount: 38_900_000,
            },
            {
                date: '2026-08-14',
                label: 'Règlement situation n°1',
                amount: 59_500_000,
            },
            {
                date: '2026-06-02',
                label: 'Acompte de démarrage (10 %)',
                amount: 48_500_000,
            },
        ],
    },
    6: {
        contacts: [
            {
                name: 'Kouadio Brou',
                role: 'Propriétaire',
                phone: '+225 07 07 12 90 45',
                email: 'k.brou@example.com',
            },
        ],
        documents: [
            { name: 'PV de réception définitive.pdf', date: '2026-03-25' },
        ],
        payments: [
            { date: '2026-03-30', label: 'Solde du marché', amount: 9_600_000 },
        ],
    },
};
