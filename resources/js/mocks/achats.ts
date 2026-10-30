import type {
    Article,
    BudgetLine,
    BudgetRevision,
    MissingArticleReport,
    PurchaseRequest,
    RequestLine,
} from '@/types/erp';

const M = 1_000_000;

type ArticleSeed = [number, string, string, string, string, number];

// [id, référence, libellé, catégorie, unité, prix FCFA]
const seeds: ArticleSeed[] = [
    [1, 'CIM-001', 'Ciment CPJ 42,5 — sac 50 kg', 'Ciment', 'sac', 6_500],
    [2, 'CIM-002', 'Ciment CPA 52,5 — sac 50 kg', 'Ciment', 'sac', 7_200],
    [3, 'FER-008', 'Fer HA 8 — barre 12 m', 'Fer', 'barre', 3_100],
    [4, 'FER-010', 'Fer HA 10 — barre 12 m', 'Fer', 'barre', 4_600],
    [5, 'FER-012', 'Fer HA 12 — barre 12 m', 'Fer', 'barre', 6_600],
    [6, 'FER-014', 'Fer HA 14 — barre 12 m', 'Fer', 'barre', 8_900],
    [7, 'FER-020', 'Fil recuit', 'Fer', 'kg', 1_100],
    [8, 'SAB-001', 'Sable de lagune', 'Sable', 'm³', 8_500],
    [9, 'SAB-002', 'Sable de carrière', 'Sable', 'm³', 7_000],
    [10, 'GRA-001', 'Gravier 5/15', 'Gravier', 'm³', 17_500],
    [11, 'GRA-002', 'Gravier 15/25', 'Gravier', 'm³', 16_500],
    [12, 'BOI-001', 'Planche de coffrage 3 m', 'Bois', 'pièce', 2_800],
    [13, 'BOI-002', 'Chevron 8×8 — 4 m', 'Bois', 'pièce', 6_500],
    [14, 'BOI-003', 'Contreplaqué marine 18 mm', 'Bois', 'panneau', 24_000],
    [
        15,
        'PEI-001',
        'Peinture vinylique blanche — seau 25 L',
        'Peinture',
        'seau',
        48_000,
    ],
    [16, 'PEI-002', 'Peinture émail — pot 4 L', 'Peinture', 'pot', 21_500],
    [17, 'PLO-001', 'Tuyau PVC Ø100 — 4 m', 'Plomberie', 'pièce', 7_800],
    [18, 'PLO-002', 'Coude PVC Ø100', 'Plomberie', 'pièce', 1_900],
    [19, 'PLO-003', 'Colle PVC — 1 kg', 'Plomberie', 'boîte', 6_000],
    [
        20,
        'ELE-001',
        'Câble 2,5 mm² — couronne 100 m',
        'Électricité',
        'couronne',
        38_000,
    ],
    [21, 'ELE-002', 'Disjoncteur 20 A', 'Électricité', 'pièce', 4_500],
    [
        22,
        'ELE-003',
        'Gaine ICTA Ø25 — couronne',
        'Électricité',
        'couronne',
        9_500,
    ],
    [23, 'QUI-001', 'Pointes 70 mm', 'Quincaillerie', 'kg', 1_400],
    [24, 'QUI-002', 'Brouette renforcée', 'Quincaillerie', 'pièce', 32_000],
    [25, 'MAC-001', 'Parpaing creux 15', 'Maçonnerie', 'unité', 450],
    [26, 'MAC-002', 'Parpaing creux 20', 'Maçonnerie', 'unité', 600],
];

export const articles: Article[] = seeds.map(
    ([id, reference, label, category, unit, price]) => ({
        id,
        reference,
        label,
        category,
        unit,
        price,
        active: id !== 24,
        updatedAt: id % 3 === 0 ? '2026-09-15' : '2026-07-01',
        history: [
            {
                date: '2026-01-10',
                price: Math.round(price * 0.93),
                by: 'Awa Koné',
                reason: 'Création de l’article',
            },
            {
                date: '2026-04-02',
                price: Math.round(price * 0.97),
                by: 'Awa Koné',
                reason: 'Révision tarifaire fournisseur',
            },
            ...(id % 3 === 0
                ? [
                      {
                          date: '2026-09-15',
                          price,
                          by: 'Awa Koné',
                          reason: 'Hausse du cours du fer et du transport',
                      },
                  ]
                : []),
            ...(id % 3 !== 0
                ? [
                      {
                          date: '2026-07-01',
                          price,
                          by: 'Awa Koné',
                          reason: 'Mise à jour annuelle',
                      },
                  ]
                : []),
        ],
    }),
);

export const articleById = (id: number): Article | undefined =>
    articles.find((a) => a.id === id);

export const categories = [...new Set(articles.map((a) => a.category))];
export const units = [
    'sac',
    'barre',
    'kg',
    'm³',
    'pièce',
    'panneau',
    'seau',
    'pot',
    'boîte',
    'couronne',
    'unité',
    'tonne',
];

const line = (
    id: number,
    articleId: number,
    quantity: number,
    needDate: string,
    urgency: RequestLine['urgency'] = 'normale',
): RequestLine => ({
    id,
    articleId,
    quantity,
    needDate,
    urgency,
});

/** Seuil de validation Direction (paramètre administration, RG8.7). */
export const DIRECTION_THRESHOLD = 5_000_000;

export const requests: PurchaseRequest[] = [
    {
        id: 1,
        number: 'DA-2026-0142',
        chantierId: 1,
        chantierName: 'Palmiers — Bloc A',
        requester: 'Séraphin Konan',
        createdAt: '2026-10-27',
        status: 'en_attente',
        justification: 'Ferraillage du plancher haut R+1, zone nord.',
        lines: [
            line(1, 5, 400, '2026-11-02', 'urgente'),
            line(2, 7, 600, '2026-11-02'),
            line(3, 1, 300, '2026-11-03'),
        ],
        needsDirection: false,
        history: [
            {
                date: '2026-10-27 08:12',
                actor: 'Séraphin Konan',
                action: 'Création (brouillon)',
            },
            {
                date: '2026-10-27 08:40',
                actor: 'Séraphin Konan',
                action: 'Soumise pour validation',
            },
        ],
    },
    {
        id: 2,
        number: 'DA-2026-0141',
        chantierId: 2,
        chantierName: 'Palmiers — Bloc B',
        requester: 'Lanciné Touré',
        createdAt: '2026-10-26',
        status: 'en_attente',
        justification: 'Béton de propreté et poteaux RDC.',
        lines: [
            line(4, 11, 120, '2026-11-04'),
            line(5, 2, 900, '2026-11-04'),
            line(6, 6, 700, '2026-11-05', 'urgente'),
        ],
        needsDirection: true,
        history: [
            {
                date: '2026-10-26 10:02',
                actor: 'Lanciné Touré',
                action: 'Création (brouillon)',
            },
            {
                date: '2026-10-26 10:30',
                actor: 'Lanciné Touré',
                action: 'Soumise pour validation',
            },
            {
                date: '2026-10-26 15:10',
                actor: 'Ibrahim Coulibaly',
                action: 'Transmise à la Direction',
                reason: 'Montant supérieur au seuil de 5 000 000 FCFA',
            },
        ],
    },
    {
        id: 3,
        number: 'DA-2026-0140',
        chantierId: 1,
        chantierName: 'Palmiers — Bloc A',
        requester: 'Séraphin Konan',
        createdAt: '2026-10-22',
        status: 'validee',
        justification: 'Maçonnerie des cloisons RDC.',
        lines: [
            line(7, 25, 4000, '2026-10-28'),
            line(8, 26, 1500, '2026-10-28'),
            line(9, 1, 150, '2026-10-28'),
        ],
        frozenPrices: { 25: 450, 26: 600, 1: 6_500 },
        needsDirection: false,
        history: [
            {
                date: '2026-10-22 09:00',
                actor: 'Séraphin Konan',
                action: 'Création (brouillon)',
            },
            {
                date: '2026-10-22 09:20',
                actor: 'Séraphin Konan',
                action: 'Soumise pour validation',
            },
            {
                date: '2026-10-23 11:45',
                actor: 'Ibrahim Coulibaly',
                action: 'Validée',
                reason: 'Prix figés, engagement de 3 675 000 FCFA inscrit au budget',
            },
        ],
    },
    {
        id: 4,
        number: 'DA-2026-0139',
        chantierId: 2,
        chantierName: 'Palmiers — Bloc B',
        requester: 'Lanciné Touré',
        createdAt: '2026-10-20',
        status: 'renvoyee',
        justification: 'Coffrage poutres.',
        lines: [
            line(10, 12, 300, '2026-10-30'),
            line(11, 13, 90, '2026-10-30'),
            line(12, 23, 40, '2026-10-30'),
        ],
        needsDirection: false,
        history: [
            {
                date: '2026-10-20 14:00',
                actor: 'Lanciné Touré',
                action: 'Soumise pour validation',
            },
            {
                date: '2026-10-21 09:30',
                actor: 'Ibrahim Coulibaly',
                action: 'Renvoyée pour correction',
                reason: 'Quantité de chevrons surévaluée : revoir le métré du coffrage.',
            },
        ],
    },
    {
        id: 5,
        number: 'DA-2026-0138',
        chantierId: 1,
        chantierName: 'Palmiers — Bloc A',
        requester: 'Mamadou Bamba',
        createdAt: '2026-10-19',
        status: 'refusee',
        justification: 'Brouette supplémentaire.',
        lines: [line(13, 24, 4, '2026-10-25')],
        needsDirection: false,
        history: [
            {
                date: '2026-10-19 16:00',
                actor: 'Mamadou Bamba',
                action: 'Soumise pour validation',
            },
            {
                date: '2026-10-20 08:15',
                actor: 'Ibrahim Coulibaly',
                action: 'Refusée',
                reason: 'Article désactivé du référentiel ; 6 brouettes déjà disponibles au magasin.',
            },
        ],
    },
    {
        id: 6,
        number: 'DA-2026-0137',
        chantierId: 3,
        chantierName: 'École de Bingerville',
        requester: 'Konan Yeboua',
        createdAt: '2026-10-28',
        status: 'brouillon',
        justification: 'Installation de chantier : base-vie.',
        lines: [
            line(14, 12, 80, '2026-11-02'),
            line(15, 23, 20, '2026-11-02'),
            line(16, 14, 10, '2026-11-02'),
        ],
        needsDirection: false,
        history: [
            {
                date: '2026-10-28 17:20',
                actor: 'Konan Yeboua',
                action: 'Création (brouillon)',
            },
        ],
    },
    {
        id: 7,
        number: 'DA-2026-0136',
        chantierId: 1,
        chantierName: 'Palmiers — Bloc A',
        requester: 'Séraphin Konan',
        createdAt: '2026-10-15',
        status: 'validee',
        justification: 'Plomberie — réseaux EU/EV.',
        lines: [
            line(17, 17, 120, '2026-11-02'),
            line(18, 18, 200, '2026-11-02'),
            line(19, 19, 30, '2026-11-02'),
        ],
        frozenPrices: { 17: 7_800, 18: 1_900, 19: 6_000 },
        needsDirection: false,
        history: [
            {
                date: '2026-10-15 09:00',
                actor: 'Séraphin Konan',
                action: 'Soumise pour validation',
            },
            {
                date: '2026-10-16 10:00',
                actor: 'Ibrahim Coulibaly',
                action: 'Validée',
            },
        ],
    },
    {
        id: 8,
        number: 'DA-2026-0135',
        chantierId: 2,
        chantierName: 'Palmiers — Bloc B',
        requester: 'Lanciné Touré',
        createdAt: '2026-10-12',
        status: 'annulee',
        justification: 'Doublon de la demande DA-2026-0133.',
        lines: [line(20, 1, 100, '2026-10-20')],
        needsDirection: false,
        history: [
            {
                date: '2026-10-12 11:00',
                actor: 'Lanciné Touré',
                action: 'Soumise pour validation',
            },
            {
                date: '2026-10-12 11:30',
                actor: 'Lanciné Touré',
                action: 'Annulée par son auteur',
                reason: 'Doublon',
            },
        ],
    },
];

export function requestTotal(request: PurchaseRequest): number {
    return request.lines.reduce((sum, l) => {
        const frozen = request.frozenPrices?.[l.articleId];
        const price = frozen ?? articleById(l.articleId)?.price ?? 0;

        return sum + price * (l.adjustedQuantity ?? l.quantity);
    }, 0);
}

export const missingArticleReports: MissingArticleReport[] = [
    {
        id: 1,
        label: 'Treillis soudé ST25 — panneau 2,4 × 6 m',
        requester: 'Séraphin Konan',
        chantier: 'Palmiers — Bloc A',
        date: '2026-10-26',
    },
    {
        id: 2,
        label: 'Hydrofuge de masse — bidon 20 L',
        requester: 'Konan Yeboua',
        chantier: 'École de Bingerville',
        date: '2026-10-28',
    },
];

const line2 = (
    id: number,
    chantierId: number,
    post: string,
    nature: BudgetLine['nature'],
    initial: number,
    revised: number,
    committed: number,
    actual: number,
): BudgetLine => ({
    id,
    chantierId,
    post,
    nature,
    initial: initial * M,
    revised: revised * M,
    committed: committed * M,
    actual: actual * M,
});

export const budgetLines: BudgetLine[] = [
    // CH-001 — consommé 81 % (avertissement)
    line2(1, 1, 'Gros œuvre', 'materiaux', 120, 128, 14, 112),
    line2(2, 1, 'Second œuvre', 'materiaux', 60, 60, 20, 20),
    line2(3, 1, 'Lots techniques', 'materiaux', 45, 45, 9, 0),
    line2(4, 1, 'Main-d’œuvre', 'main_oeuvre', 70, 72, 0, 70),
    line2(5, 1, 'Engins et matériel', 'materiel', 25, 25, 2, 20),
    // CH-002 — dépassement (alerte critique)
    line2(6, 2, 'Gros œuvre', 'materiaux', 100, 100, 20, 105),
    line2(7, 2, 'Second œuvre', 'materiaux', 50, 50, 38, 20),
    line2(8, 2, 'Lots techniques', 'materiaux', 40, 40, 5, 2),
    line2(9, 2, 'Main-d’œuvre', 'main_oeuvre', 60, 60, 0, 62),
    line2(10, 2, 'Engins et matériel', 'materiel', 20, 20, 1, 18),
    // CH-003 — démarrage
    line2(11, 3, 'Gros œuvre', 'materiaux', 150, 150, 0, 12),
    line2(12, 3, 'Second œuvre', 'materiaux', 80, 80, 0, 0),
    line2(13, 3, 'Lots techniques', 'materiaux', 60, 60, 0, 0),
    line2(14, 3, 'Main-d’œuvre', 'main_oeuvre', 90, 90, 0, 6),
    line2(15, 3, 'Engins et matériel', 'materiel', 30, 30, 0, 4),
    // CH-004 — VRD suspendu
    line2(16, 4, 'Terrassement et caniveaux', 'materiaux', 38, 38, 3, 14),
    line2(17, 4, 'Main-d’œuvre', 'main_oeuvre', 22, 22, 0, 9),
];

export const budgetRevisions: BudgetRevision[] = [
    {
        date: '2026-08-28',
        chantierId: 1,
        post: 'Gros œuvre',
        amount: 8 * M,
        reason: 'Avenant n°1 du marché : ajout d’un voile de contreventement.',
        author: 'Ibrahim Coulibaly',
        validatedBy: 'Direction technique',
    },
    {
        date: '2026-09-30',
        chantierId: 1,
        post: 'Main-d’œuvre',
        amount: 2 * M,
        reason: 'Heures supplémentaires pour rattraper le retard du plancher.',
        author: 'Ibrahim Coulibaly',
        validatedBy: 'Direction générale',
    },
];

export function budgetTotals(lines: BudgetLine[]) {
    const initial = lines.reduce((s, l) => s + l.initial, 0);
    const revised = lines.reduce((s, l) => s + l.revised, 0);
    const committed = lines.reduce((s, l) => s + l.committed, 0);
    const actual = lines.reduce((s, l) => s + l.actual, 0);
    const remaining = revised - committed - actual;
    const consumption =
        revised === 0 ? 0 : ((committed + actual) / revised) * 100;

    return { initial, revised, committed, actual, remaining, consumption };
}

/** Seuils d'alerte (RG10.3) : avertissement à 80 %, alerte critique à 100 %. */
export type BudgetLevel = 'ok' | 'warning' | 'critical';

export function budgetLevel(consumption: number): BudgetLevel {
    if (consumption >= 100) {
        return 'critical';
    }

    return consumption >= 80 ? 'warning' : 'ok';
}

export const budgetOf = (chantierId: number): BudgetLine[] =>
    budgetLines.filter((l) => l.chantierId === chantierId);

/** Dépenses diverses d'un chantier (petits achats, transport) : justificatif obligatoire (RG10.4). */
export type BudgetExpense = {
    id: number;
    chantierId: number;
    date: string;
    label: string;
    post: string;
    amount: number;
    receipt: string;
    author: string;
};

export const budgetExpenses: BudgetExpense[] = [
    {
        id: 1,
        chantierId: 1,
        date: '2026-10-28',
        label: 'Location d’une bétonnière (2 jours)',
        post: 'Engins et matériel',
        amount: 90_000,
        receipt: 'facture-location-0412.pdf',
        author: 'Séraphin Konan',
    },
    {
        id: 2,
        chantierId: 1,
        date: '2026-10-26',
        label: 'Transport de sable',
        post: 'Gros œuvre',
        amount: 150_000,
        receipt: 'recu-transport-t-0089.jpg',
        author: 'Séraphin Konan',
    },
    {
        id: 3,
        chantierId: 2,
        date: '2026-10-27',
        label: 'Outillage (disques, gants)',
        post: 'Second œuvre',
        amount: 64_500,
        receipt: 'ticket-4471.jpg',
        author: 'Lanciné Touré',
    },
];
