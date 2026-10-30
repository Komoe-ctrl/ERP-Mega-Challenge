import type {
    BankCommitment,
    CashEntry,
    CashVoucher,
    DeliveryNote,
    EngagedAccount,
    ExportRow,
    ImportedDataset,
    LegacyInvoice,
    LegacyService,
    Piece,
    PieceLine,
    ProjectTree,
    Quotation,
    Receivable,
    Rubrique,
    SupplierDebt,
    WorkflowStep,
} from '@/types/legacy';

export const services: LegacyService[] = [
    {
        id: 'admin',
        name: "Chargé de l'administration et du contrôle",
        shortName: 'ADMINISTRATION ET CONTROLE',
        titles: ['Chargé de l’administration et du contrôle'],
    },
    {
        id: 'pdg',
        name: 'Président Directeur Général',
        shortName: 'PRESIDENT DIRECTEUR GENERAL',
        titles: ['Président Directeur Général'],
    },
    {
        id: 'btp',
        name: 'Secrétariat chargé du BTP',
        shortName: 'SECRETARIAT CHARGE DU BTP-HOTELLERIE',
        titles: [
            'SG1 – Chargé des projets immobiliers',
            'Assistante SG1',
            'Technicien 1',
            'Technicien 2',
            'Directeur Mega Challenge',
        ],
    },
];

export const exercices = [2024, 2025, 2026, 2027];

/** Circuit de validation par défaut (paramétrable dans Fichier > Étapes de validation). */
export const defaultWorkflow: WorkflowStep[] = [
    {
        id: 1,
        label: 'Saisie',
        title: 'Technicien 1',
        button: 'Valider',
        enabled: true,
    },
    {
        id: 2,
        label: 'Validation du chef de service',
        title: 'SG1 – Chargé des projets immobiliers',
        button: 'SG1 – Chargé des projets immobiliers',
        enabled: true,
    },
    {
        id: 3,
        label: 'Approbation du Directeur Mega Challenge',
        title: 'Directeur Mega Challenge',
        button: 'Directeur Mega Challenge',
        enabled: true,
    },
];

export const rubriques: Rubrique[] = [
    {
        code: '601',
        label: 'Matériaux de construction',
        budget: 180_000_000,
        consumed: 96_400_000,
        committed: 41_200_000,
    },
    {
        code: '604',
        label: 'Fournitures de chantier',
        budget: 36_000_000,
        consumed: 18_900_000,
        committed: 9_300_000,
    },
    {
        code: '613',
        label: 'Location d’engins',
        budget: 48_000_000,
        consumed: 41_500_000,
        committed: 7_800_000,
    },
    {
        code: '622',
        label: 'Honoraires et prestations',
        budget: 24_000_000,
        consumed: 9_100_000,
        committed: 2_400_000,
    },
    {
        code: '625',
        label: 'Déplacements et missions',
        budget: 12_000_000,
        consumed: 6_700_000,
        committed: 1_100_000,
    },
    {
        code: '626',
        label: 'Frais postaux et télécommunications',
        budget: 4_800_000,
        consumed: 2_900_000,
        committed: 0,
    },
];

export const projectTrees: ProjectTree[] = [
    {
        project: 'Résidence Les Palmiers',
        products: [
            {
                name: 'Lot 1 – Gros œuvre',
                components: ['Fondations', 'Élévation', 'Dalles'],
            },
            {
                name: 'Lot 2 – Second œuvre',
                components: ['Plomberie', 'Électricité', 'Peinture'],
            },
        ],
    },
    {
        project: 'Hôtel Lagune',
        products: [
            {
                name: 'Section A – Hébergement',
                components: ['Chambres', 'Couloirs'],
            },
            {
                name: 'Section B – Restauration',
                components: ['Cuisine', 'Salle'],
            },
        ],
    },
    {
        project: 'Siège administratif',
        products: [
            { name: 'Lot unique', components: ['Structure', 'Finitions'] },
        ],
    },
];

const L = (
    designation: string,
    quantity: number,
    unitPrice: number,
    rubrique: string,
): PieceLine => ({ designation, quantity, unitPrice, rubrique });

const ev = (at: string, by: string, action: string, comment = '') => ({
    at,
    by,
    action,
    comment,
});

export const initialPieces: Piece[] = [
    {
        id: 1,
        kind: 'da',
        number: 'DA-2026-0142',
        title: 'Ciment et fer pour dalle R+1',
        period: '2026-10',
        date: '2026-10-26',
        requester: 'Technicien 1',
        bcNumber: '',
        observation: 'Livraison souhaitée avant le 5 novembre.',
        proof: 'devis-ciment.pdf',
        project: 'Résidence Les Palmiers',
        product: 'Lot 1 – Gros œuvre',
        component: 'Dalles',
        status: 'saisie',
        lines: [
            L('Ciment CPJ 42,5 — sac 50 kg', 400, 6_500, '601'),
            L('Fer HA 12 — barre 12 m', 220, 6_600, '601'),
        ],
        history: [ev('2026-10-26 09:12', 'Technicien 1', 'Saisie')],
    },
    {
        id: 2,
        kind: 'da',
        number: 'DA-2026-0139',
        title: 'Peinture hall d’entrée',
        period: '2026-10',
        date: '2026-10-22',
        requester: 'Technicien 2',
        bcNumber: 'BC-2026-0088',
        observation: '',
        proof: '',
        project: 'Siège administratif',
        product: 'Lot unique',
        component: 'Finitions',
        status: 'validee',
        lines: [L('Peinture vinylique blanche — seau 25 L', 12, 48_000, '604')],
        history: [
            ev('2026-10-22 10:03', 'Technicien 2', 'Saisie'),
            ev(
                '2026-10-23 08:40',
                'SG1 – Chargé des projets immobiliers',
                'Validation du chef de service',
                'Conforme au budget.',
            ),
        ],
    },
    {
        id: 3,
        kind: 'da',
        number: 'DA-2026-0131',
        title: 'Location pelle hydraulique — 2 semaines',
        period: '2026-10',
        date: '2026-10-15',
        requester: 'Technicien 1',
        bcNumber: 'BC-2026-0081',
        observation: 'Dépasse la rubrique 613 : arbitrage demandé.',
        proof: 'proforma-btp-loc.pdf',
        project: 'Hôtel Lagune',
        product: 'Section A – Hébergement',
        component: 'Chambres',
        status: 'approuvee',
        lines: [L('Pelle hydraulique 20 t — jour', 14, 380_000, '613')],
        history: [
            ev('2026-10-15 11:20', 'Technicien 1', 'Saisie'),
            ev(
                '2026-10-16 09:00',
                'SG1 – Chargé des projets immobiliers',
                'Validation du chef de service',
            ),
            ev(
                '2026-10-17 15:45',
                'Directeur Mega Challenge',
                'Approbation',
                'Accordé.',
            ),
        ],
    },
    {
        id: 4,
        kind: 'nf',
        number: 'NF-2026-0057',
        title: 'Mission de suivi — Bouaké',
        period: '2026-10',
        date: '2026-10-27',
        requester: 'Technicien 1',
        bcNumber: '',
        observation: 'Aller-retour + hébergement 2 nuits.',
        proof: 'recus-mission.pdf',
        project: 'Résidence Les Palmiers',
        product: 'Lot 1 – Gros œuvre',
        component: 'Fondations',
        status: 'validee',
        lines: [
            L('Transport aller-retour', 2, 18_000, '625'),
            L('Hébergement', 2, 35_000, '625'),
            L('Repas', 4, 6_500, '625'),
        ],
        history: [
            ev('2026-10-27 17:05', 'Technicien 1', 'Saisie'),
            ev(
                '2026-10-28 08:30',
                'SG1 – Chargé des projets immobiliers',
                'Validation du chef de service',
            ),
        ],
    },
    {
        id: 5,
        kind: 'nf',
        number: 'NF-2026-0054',
        title: 'Carburant véhicule de chantier',
        period: '2026-10',
        date: '2026-10-20',
        requester: 'Technicien 2',
        bcNumber: '',
        observation: '',
        proof: '',
        project: 'Hôtel Lagune',
        product: 'Section B – Restauration',
        component: 'Cuisine',
        status: 'saisie',
        lines: [L('Gasoil', 120, 755, '604')],
        history: [ev('2026-10-20 18:10', 'Technicien 2', 'Saisie')],
    },
    {
        id: 6,
        kind: 'recette',
        number: 'RE-2026-0031',
        title: 'Situation n° 4 — Résidence Les Palmiers',
        period: '2026-10',
        date: '2026-10-25',
        requester: 'Technicien 1',
        bcNumber: '',
        observation: 'Facture client émise le 25/10.',
        proof: 'situation-4.pdf',
        project: 'Résidence Les Palmiers',
        product: 'Lot 1 – Gros œuvre',
        component: 'Élévation',
        status: 'validee',
        lines: [L('Situation de travaux n° 4', 1, 62_500_000, '701')],
        history: [
            ev('2026-10-25 14:00', 'Technicien 1', 'Saisie'),
            ev(
                '2026-10-26 09:10',
                'SG1 – Chargé des projets immobiliers',
                'Validation du chef de service',
            ),
        ],
    },
    {
        id: 7,
        kind: 'caisse',
        number: 'CA-2026-0212',
        title: 'Achat petit outillage',
        period: '2026-10',
        date: '2026-10-28',
        requester: 'Assistante SG1',
        bcNumber: '',
        observation: '',
        proof: 'ticket-quincaillerie.jpg',
        project: 'Siège administratif',
        product: 'Lot unique',
        component: 'Finitions',
        status: 'saisie',
        lines: [L('Petit outillage', 1, 48_500, '604')],
        history: [ev('2026-10-28 12:30', 'Assistante SG1', 'Saisie')],
    },
];

export const quotations: Quotation[] = [
    {
        id: 1,
        number: 'COT-2026-0061',
        daNumber: 'DA-2026-0142',
        suppliers: [
            { name: 'Ivoire Matériaux', amount: 4_050_000, delay: 4 },
            { name: 'SOGEBAT', amount: 4_210_000, delay: 3 },
            { name: 'Comptoir du Bâtiment', amount: 3_980_000, delay: 7 },
        ],
        status: 'en_cours',
    },
    {
        id: 2,
        number: 'COT-2026-0058',
        daNumber: 'DA-2026-0139',
        suppliers: [
            { name: 'Peintures d’Afrique', amount: 576_000, delay: 2 },
            { name: 'Couleurs Abidjan', amount: 610_000, delay: 2 },
        ],
        status: 'a_valider',
    },
    {
        id: 3,
        number: 'COT-2026-0049',
        daNumber: 'DA-2026-0131',
        suppliers: [
            { name: 'BTP Location', amount: 5_320_000, delay: 1 },
            { name: 'Engins CI', amount: 5_600_000, delay: 2 },
        ],
        status: 'validee',
    },
];

export const engagedAccounts: EngagedAccount[] = [
    {
        id: 1,
        number: 'BC-2026-0088',
        rubrique: '604',
        engaged: 576_000,
        approvedAt: null,
    },
    {
        id: 2,
        number: 'BC-2026-0081',
        rubrique: '613',
        engaged: 5_320_000,
        approvedAt: '2026-10-18',
    },
    {
        id: 3,
        number: 'BC-2026-0079',
        rubrique: '601',
        engaged: 12_450_000,
        approvedAt: '2026-10-12',
    },
    {
        id: 4,
        number: 'BC-2026-0092',
        rubrique: '601',
        engaged: 3_980_000,
        approvedAt: null,
    },
];

export const deliveryNotes: DeliveryNote[] = [
    {
        id: 1,
        number: 'BL-2026-0201',
        bcNumber: 'BC-2026-0079',
        supplier: 'Ivoire Matériaux',
        date: '2026-10-20',
        lines: 3,
        status: 'recu',
    },
    {
        id: 2,
        number: 'BL-2026-0205',
        bcNumber: 'BC-2026-0081',
        supplier: 'BTP Location',
        date: '2026-10-26',
        lines: 1,
        status: 'partiel',
    },
    {
        id: 3,
        number: 'BL-2026-0209',
        bcNumber: 'BC-2026-0088',
        supplier: 'Peintures d’Afrique',
        date: '2026-11-03',
        lines: 1,
        status: 'attendu',
    },
];

export const legacyInvoices: LegacyInvoice[] = [
    {
        id: 1,
        number: 'FA-2026-0310',
        kind: 'achat',
        party: 'Ivoire Matériaux',
        date: '2026-10-21',
        amount: 12_450_000,
        status: 'a_payer',
    },
    {
        id: 2,
        number: 'FA-2026-0307',
        kind: 'achat',
        party: 'BTP Location',
        date: '2026-10-19',
        amount: 2_660_000,
        status: 'payee',
    },
    {
        id: 3,
        number: 'FN-2026-0042',
        kind: 'note_frais',
        party: 'Technicien 1',
        date: '2026-10-28',
        amount: 142_000,
        status: 'a_payer',
    },
];

export const receivables: Receivable[] = [
    {
        id: 1,
        client: 'Groupe Atlantique Immo',
        invoice: 'FC-2026-0115',
        amount: 62_500_000,
        collected: 40_000_000,
        dueDate: '2026-11-10',
        reminders: 0,
    },
    {
        id: 2,
        client: 'SIPIM',
        invoice: 'FC-2026-0108',
        amount: 28_900_000,
        collected: 0,
        dueDate: '2026-10-15',
        reminders: 2,
    },
    {
        id: 3,
        client: 'Hôtel Lagune SA',
        invoice: 'FC-2026-0102',
        amount: 18_400_000,
        collected: 18_400_000,
        dueDate: '2026-10-05',
        reminders: 0,
    },
];

export const cashEntries: CashEntry[] = [
    {
        id: 1,
        date: '2026-10-26',
        label: 'Encaissement acompte client',
        kind: 'entree',
        amount: 1_500_000,
        by: 'Assistante SG1',
        justified: true,
    },
    {
        id: 2,
        date: '2026-10-27',
        label: 'Achat petit outillage',
        kind: 'sortie',
        amount: 48_500,
        by: 'Assistante SG1',
        justified: false,
    },
    {
        id: 3,
        date: '2026-10-28',
        label: 'Avance mission Bouaké',
        kind: 'sortie',
        amount: 150_000,
        by: 'Technicien 1',
        justified: false,
    },
    {
        id: 4,
        date: '2026-10-29',
        label: 'Remboursement trop-perçu',
        kind: 'entree',
        amount: 35_000,
        by: 'Assistante SG1',
        justified: true,
    },
];

export const cashVouchers: CashVoucher[] = [
    {
        id: 1,
        number: 'BD-2026-0044',
        date: '2026-10-28',
        label: 'Paiement journaliers chantier',
        kind: 'decaisser',
        amount: 820_000,
        status: 'a_traiter',
    },
    {
        id: 2,
        number: 'BE-2026-0019',
        date: '2026-10-29',
        label: 'Acompte client Hôtel Lagune',
        kind: 'encaisser',
        amount: 5_000_000,
        status: 'a_traiter',
    },
    {
        id: 3,
        number: 'BD-2026-0041',
        date: '2026-10-24',
        label: 'Frais de transport',
        kind: 'decaisser',
        amount: 64_000,
        status: 'traite',
    },
];

export const supplierDebts: SupplierDebt[] = [
    {
        id: 1,
        supplier: 'Ivoire Matériaux',
        bcNumber: 'BC-2026-0079',
        amount: 12_450_000,
        paid: 6_000_000,
        dueDate: '2026-11-20',
    },
    {
        id: 2,
        supplier: 'BTP Location',
        bcNumber: 'BC-2026-0081',
        amount: 5_320_000,
        paid: 2_660_000,
        dueDate: '2026-11-05',
    },
    {
        id: 3,
        supplier: 'Peintures d’Afrique',
        bcNumber: 'BC-2026-0088',
        amount: 576_000,
        paid: 0,
        dueDate: '2026-12-01',
    },
];

export const bankCommitments: BankCommitment[] = [
    {
        id: 1,
        bank: 'SGBCI',
        label: 'Caution de soumission — marché Palmiers',
        amount: 8_000_000,
        dueDate: '2026-12-31',
        kind: 'caution',
    },
    {
        id: 2,
        bank: 'NSIA Banque',
        label: 'Crédit de campagne',
        amount: 60_000_000,
        dueDate: '2027-03-31',
        kind: 'credit',
    },
    {
        id: 3,
        bank: 'Ecobank',
        label: 'Lettre de change — Ivoire Matériaux',
        amount: 6_450_000,
        dueDate: '2026-11-30',
        kind: 'lettre_change',
    },
];

export const saariCharges: ExportRow[] = [
    {
        id: 1,
        date: '2026-10-21',
        journal: 'ACH',
        account: '601100',
        label: 'FA-2026-0310 Ivoire Matériaux',
        debit: 12_450_000,
        credit: 0,
    },
    {
        id: 2,
        date: '2026-10-21',
        journal: 'ACH',
        account: '401100',
        label: 'FA-2026-0310 Ivoire Matériaux',
        debit: 0,
        credit: 12_450_000,
    },
    {
        id: 3,
        date: '2026-10-28',
        journal: 'OD',
        account: '625100',
        label: 'NF-2026-0057 Mission Bouaké',
        debit: 142_000,
        credit: 0,
    },
    {
        id: 4,
        date: '2026-10-28',
        journal: 'OD',
        account: '421000',
        label: 'NF-2026-0057 Mission Bouaké',
        debit: 0,
        credit: 142_000,
    },
];

export const saariRecettes: ExportRow[] = [
    {
        id: 1,
        date: '2026-10-25',
        journal: 'VTE',
        account: '411100',
        label: 'FC-2026-0115 Groupe Atlantique Immo',
        debit: 62_500_000,
        credit: 0,
    },
    {
        id: 2,
        date: '2026-10-25',
        journal: 'VTE',
        account: '701100',
        label: 'FC-2026-0115 Situation n° 4',
        debit: 0,
        credit: 62_500_000,
    },
];

export const generalLedger: ExportRow[] = [
    ...saariCharges,
    ...saariRecettes,
    {
        id: 9,
        date: '2026-10-26',
        journal: 'CAI',
        account: '571000',
        label: 'Encaissement acompte client',
        debit: 1_500_000,
        credit: 0,
    },
    {
        id: 10,
        date: '2026-10-26',
        journal: 'CAI',
        account: '411100',
        label: 'Encaissement acompte client',
        debit: 0,
        credit: 1_500_000,
    },
];

export const importedDatasets: ImportedDataset[] = [
    {
        id: 1,
        name: 'Balance_septembre_2026.xlsx',
        importedAt: '2026-10-03',
        rows: 214,
        mapped: true,
    },
    {
        id: 2,
        name: 'Realise_T3_2026.csv',
        importedAt: '2026-10-08',
        rows: 1_042,
        mapped: true,
    },
    {
        id: 3,
        name: 'Fournisseurs_export.csv',
        importedAt: '2026-10-19',
        rows: 87,
        mapped: false,
    },
];

/** Dernières saisies du mois — alimente le tableau de bord mensuel. */
export const monthlyFigures = [
    { month: 'Mai', budget: 31, realise: 27 },
    { month: 'Juin', budget: 34, realise: 36 },
    { month: 'Juil.', budget: 33, realise: 31 },
    { month: 'Août', budget: 30, realise: 28 },
    { month: 'Sept.', budget: 36, realise: 39 },
    { month: 'Oct.', budget: 38, realise: 33 },
];

export const pieceTotal = (lines: PieceLine[]): number =>
    lines.reduce((sum, l) => sum + l.quantity * l.unitPrice, 0);

export const rubriqueByCode = (code: string): Rubrique | undefined =>
    rubriques.find((r) => r.code === code);

export const pieceKindLabels = {
    da: 'D.A.',
    nf: 'note de frais',
    recette: 'recette',
    caisse: 'pièce de caisse',
} as const;
