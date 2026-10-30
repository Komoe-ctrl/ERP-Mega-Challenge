import type {
    ActivityEntry,
    Agency,
    AppNotification,
    Service,
    SettingGroup,
    SiteRecord,
} from '@/types/erp';

export const notifications: AppNotification[] = [
    {
        id: 1,
        kind: 'demande',
        title: 'Nouvelle demande à valider',
        body: 'DA-2026-0142 — Palmiers Bloc A (Séraphin Konan), 4 060 000 FCFA.',
        date: '2026-10-29 08:12',
        read: false,
        href: '/achats/demandes/1',
    },
    {
        id: 2,
        kind: 'budget',
        title: 'Seuil budgétaire atteint (100 %)',
        body: 'Palmiers — Bloc B : 100,4 % du budget révisé consommé.',
        date: '2026-10-29 07:30',
        read: false,
        href: '/budget/2',
    },
    {
        id: 3,
        kind: 'planning',
        title: 'Tâche en retard',
        body: 'Gros œuvre RDC et R+1 (Bloc A) : fin prévue le 15/10/2026, avancement 90 %.',
        date: '2026-10-29 06:00',
        read: false,
        href: '/chantiers/1',
    },
    {
        id: 4,
        kind: 'planning',
        title: 'Tâche non renseignée',
        body: 'Maçonnerie des cloisons (Bloc A) : aucune mise à jour depuis 11 jours.',
        date: '2026-10-29 06:00',
        read: false,
        href: '/chantiers/1',
    },
    {
        id: 5,
        kind: 'decision',
        title: 'Demande validée',
        body: 'DA-2026-0140 a été validée par Ibrahim Coulibaly.',
        date: '2026-10-23 11:45',
        read: true,
        href: '/achats/demandes/3',
    },
    {
        id: 6,
        kind: 'decision',
        title: 'Demande renvoyée pour correction',
        body: 'DA-2026-0139 : « Quantité de chevrons surévaluée ».',
        date: '2026-10-21 09:30',
        read: true,
        href: '/achats/demandes/4',
    },
    {
        id: 7,
        kind: 'budget',
        title: 'Avertissement budgétaire (80 %)',
        body: 'Palmiers — Bloc A : 80,9 % du budget révisé consommé.',
        date: '2026-10-20 07:30',
        read: true,
        href: '/budget/1',
    },
    {
        id: 8,
        kind: 'stock',
        title: 'Stock sous le seuil minimal',
        body: 'Ciment CPJ 42,5 — dépôt central : 120 sacs (minimum 300).',
        date: '2026-10-19 16:00',
        read: true,
        href: '/stocks',
    },
    {
        id: 9,
        kind: 'engin',
        title: 'Maintenance d’engin à échéance',
        body: 'Pelle hydraulique PH-02 : vidange à 1 500 h (échéance 02/11/2026).',
        date: '2026-10-18 07:00',
        read: true,
        href: '/engins/2',
    },
    {
        id: 10,
        kind: 'echeance',
        title: 'Facture fournisseur à échéance',
        body: 'FAC-F-2026-091 (Sotraci Matériaux) : échéance le 31/10/2026.',
        date: '2026-10-17 07:00',
        read: true,
        href: '/achats/factures',
    },
];

export const activityLog: ActivityEntry[] = [
    {
        id: 1,
        date: '2026-10-29 08:40',
        actor: 'Séraphin Konan',
        event: 'modification',
        subject: 'Tâche « Plancher haut R+1 »',
        changes: [{ field: 'Avancement', before: '45 %', after: '55 %' }],
    },
    {
        id: 2,
        date: '2026-10-29 08:12',
        actor: 'Séraphin Konan',
        event: 'creation',
        subject: 'Demande d’achat DA-2026-0142',
        changes: [{ field: 'Statut', before: '—', after: 'En attente' }],
    },
    {
        id: 3,
        date: '2026-10-28 16:20',
        actor: 'Awa Koné',
        event: 'modification',
        subject: 'Article CIM-001 — Ciment CPJ 42,5',
        changes: [
            {
                field: 'Prix unitaire',
                before: '6 300 FCFA',
                after: '6 500 FCFA',
            },
        ],
    },
    {
        id: 4,
        date: '2026-10-23 11:45',
        actor: 'Ibrahim Coulibaly',
        event: 'validation',
        subject: 'Demande d’achat DA-2026-0140',
        changes: [
            { field: 'Statut', before: 'En attente', after: 'Validée' },
            { field: 'Engagement', before: '0 FCFA', after: '3 675 000 FCFA' },
        ],
    },
    {
        id: 5,
        date: '2026-10-21 09:30',
        actor: 'Ibrahim Coulibaly',
        event: 'modification',
        subject: 'Demande d’achat DA-2026-0139',
        changes: [{ field: 'Statut', before: 'En attente', after: 'Renvoyée' }],
    },
    {
        id: 6,
        date: '2026-10-20 08:15',
        actor: 'Ibrahim Coulibaly',
        event: 'modification',
        subject: 'Demande d’achat DA-2026-0138',
        changes: [{ field: 'Statut', before: 'En attente', after: 'Refusée' }],
    },
    {
        id: 7,
        date: '2026-10-15 10:02',
        actor: 'Administrateur',
        event: 'creation',
        subject: 'Utilisateur Lanciné Touré',
        changes: [
            { field: 'Rôle', before: '—', after: 'Chef de chantier' },
            { field: 'Agence', before: '—', after: 'Siège Abidjan' },
        ],
    },
    {
        id: 8,
        date: '2026-10-14 14:30',
        actor: 'Administrateur',
        event: 'modification',
        subject: 'Paramètre budget.warning_percent',
        changes: [{ field: 'Valeur', before: '75', after: '80' }],
    },
    {
        id: 9,
        date: '2026-10-10 09:00',
        actor: 'Administrateur',
        event: 'modification',
        subject: 'Rôle Magasinier',
        changes: [
            {
                field: 'Permissions',
                before: 'stocks.view',
                after: 'stocks.view, stocks.create, stocks.update',
            },
        ],
    },
    {
        id: 10,
        date: '2026-09-30 18:00',
        actor: 'Direction générale',
        event: 'validation',
        subject: 'Révision de budget — Bloc A / Main-d’œuvre',
        changes: [
            {
                field: 'Budget révisé',
                before: '70 000 000 FCFA',
                after: '72 000 000 FCFA',
            },
        ],
    },
    {
        id: 11,
        date: '2026-09-15 09:12',
        actor: 'Awa Koné',
        event: 'modification',
        subject: 'Article FER-012 — Fer HA 12',
        changes: [
            {
                field: 'Prix unitaire',
                before: '6 400 FCFA',
                after: '6 600 FCFA',
            },
        ],
    },
    {
        id: 12,
        date: '2026-09-01 08:00',
        actor: 'Administrateur',
        event: 'creation',
        subject: 'Exercice comptable 2026',
        changes: [
            { field: 'Période', before: '—', after: '01/01/2026 – 31/12/2026' },
        ],
    },
];

export const agencies: Agency[] = [
    {
        id: 1,
        code: 'ABJ',
        name: 'Siège Abidjan',
        city: 'Abidjan',
        active: true,
    },
    { id: 2, code: 'BKE', name: 'Agence Bouaké', city: 'Bouaké', active: true },
    {
        id: 3,
        code: 'SPD',
        name: 'Agence San-Pédro',
        city: 'San-Pédro',
        active: false,
    },
];

export const services: Service[] = [
    { id: 1, code: 'DG', name: 'Direction générale', users: 2, active: true },
    { id: 2, code: 'DT', name: 'Direction technique', users: 3, active: true },
    { id: 3, code: 'ACH', name: 'Achats BTP', users: 4, active: true },
    { id: 4, code: 'TRV', name: 'Travaux', users: 14, active: true },
    { id: 5, code: 'MAG', name: 'Magasin', users: 5, active: true },
    { id: 6, code: 'CPT', name: 'Comptabilité', users: 3, active: true },
    { id: 7, code: 'FIN', name: 'Finance', users: 2, active: true },
    { id: 8, code: 'RH', name: 'Ressources humaines', users: 2, active: true },
    { id: 9, code: 'ADM', name: 'Administration', users: 1, active: true },
];

export const sites: SiteRecord[] = [
    {
        id: 1,
        code: 'ABJ-SIEGE',
        name: 'Siège',
        agency: 'Siège Abidjan',
        type: 'bureau',
        active: true,
    },
    {
        id: 2,
        code: 'ABJ-DEPOT',
        name: 'Dépôt central',
        agency: 'Siège Abidjan',
        type: 'depot_central',
        active: true,
    },
    {
        id: 3,
        code: 'MAG-CH001',
        name: 'Magasin Palmiers Bloc A',
        agency: 'Siège Abidjan',
        type: 'magasin_chantier',
        active: true,
    },
    {
        id: 4,
        code: 'MAG-CH002',
        name: 'Magasin Palmiers Bloc B',
        agency: 'Siège Abidjan',
        type: 'magasin_chantier',
        active: true,
    },
    {
        id: 5,
        code: 'BKE-BUREAU',
        name: 'Bureau Bouaké',
        agency: 'Agence Bouaké',
        type: 'bureau',
        active: true,
    },
];

export const settingGroups: SettingGroup[] = [
    {
        group: 'Seuils de validation',
        items: [
            {
                key: 'validation.direction_threshold_fcfa',
                label: 'Seuil de validation Direction',
                value: '5000000',
                unit: 'FCFA',
                description:
                    'Au-delà, la demande d’achat doit être validée par la Direction (RG8.7).',
            },
            {
                key: 'validation.consultation_threshold_fcfa',
                label: 'Seuil de consultation obligatoire',
                value: '2000000',
                unit: 'FCFA',
                description:
                    'Au-delà, au moins trois fournisseurs sont consultés (RG8.11).',
            },
            {
                key: 'validation.consultation_min_suppliers',
                label: 'Fournisseurs consultés au minimum',
                value: '3',
            },
        ],
    },
    {
        group: 'Alertes',
        items: [
            {
                key: 'budget.warning_percent',
                label: 'Avertissement budgétaire',
                value: '80',
                unit: '%',
                description: 'RG10.3.',
            },
            {
                key: 'budget.critical_percent',
                label: 'Alerte budgétaire critique',
                value: '100',
                unit: '%',
                description: 'RG10.3.',
            },
            {
                key: 'planning.risk_threshold_points',
                label: 'Seuil « tâche à risque »',
                value: '15',
                unit: 'points',
                description: 'RG7.6.',
            },
            {
                key: 'planning.stale_days',
                label: 'Délai « tâche non renseignée »',
                value: '7',
                unit: 'jours',
                description: 'RG7.7.',
            },
        ],
    },
    {
        group: 'Taxes',
        items: [
            {
                key: 'tax.vat_rate',
                label: 'Taux de TVA',
                value: '18',
                unit: '%',
            },
        ],
    },
    {
        group: 'Numérotation des documents',
        items: [
            {
                key: 'numbering.devis',
                label: 'Devis',
                value: 'DEV-{YYYY}-{SEQ:5}',
            },
            {
                key: 'numbering.bon_commande',
                label: 'Bons de commande',
                value: 'BC-{YYYY}-{SEQ:5}',
            },
            {
                key: 'numbering.situation',
                label: 'Situations de travaux',
                value: 'SIT-{YYYY}-{SEQ:4}',
            },
        ],
    },
    {
        group: 'Documents',
        items: [
            {
                key: 'documents.max_size_mb',
                label: 'Taille maximale d’une pièce jointe',
                value: '20',
                unit: 'Mo',
            },
        ],
    },
];

export const fiscalYears = [
    {
        id: 1,
        label: '2025',
        startsOn: '2025-01-01',
        endsOn: '2025-12-31',
        status: 'closed' as const,
    },
    {
        id: 2,
        label: '2026',
        startsOn: '2026-01-01',
        endsOn: '2026-12-31',
        status: 'open' as const,
    },
];
