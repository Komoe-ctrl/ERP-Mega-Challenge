/** Menus de l'application actuelle, avec les libellés d'origine. */

export type LegacyLink = {
    label: string;
    slug: string;
    /** Permission minimale pour voir l'entrée (filtrage par rôle). */
    permission: string;
};

export type LegacyGroup = {
    key: string;
    label: string;
    items: LegacyLink[];
};

export const href = (slug: string): string => `/ecrans/${slug}`;

export const rubriquesMenu: LegacyGroup[] = [
    {
        key: 'da',
        label: "Demandes d'achats (D.A.)",
        items: [
            {
                label: "Demande d'achat",
                slug: 'demande-achat',
                permission: 'achats.view',
            },
            {
                label: 'Élaborer une cotation',
                slug: 'elaborer-cotation',
                permission: 'achats.view',
            },
            {
                label: 'Valider une cotation',
                slug: 'valider-cotation',
                permission: 'achats.validate',
            },
            {
                label: 'Bon de commande',
                slug: 'bon-de-commande',
                permission: 'achats.view',
            },
            {
                label: "Convertir une demande d'achat",
                slug: 'convertir-da',
                permission: 'achats.view',
            },
            {
                label: 'Approbation des comptes des pièces engagées',
                slug: 'approbation-pieces-engagees',
                permission: 'achats.validate',
            },
            {
                label: 'Validation',
                slug: 'validation-da',
                permission: 'achats.validate',
            },
            {
                label: 'Bon de livraison',
                slug: 'bon-de-livraison',
                permission: 'achats.view',
            },
            {
                label: 'Facture',
                slug: 'facture-achat',
                permission: 'achats.view',
            },
            {
                label: 'Suivi « workflow »',
                slug: 'suivi-workflow-da',
                permission: 'achats.view',
            },
        ],
    },
    {
        key: 'nf',
        label: 'Notes de frais',
        items: [
            {
                label: 'Note de frais',
                slug: 'note-de-frais',
                permission: 'achats.view',
            },
            {
                label: 'Validation',
                slug: 'validation-nf',
                permission: 'achats.validate',
            },
            { label: 'Facture', slug: 'facture-nf', permission: 'achats.view' },
            {
                label: 'Suivi « workflow »',
                slug: 'suivi-workflow-nf',
                permission: 'achats.view',
            },
        ],
    },
    {
        key: 'recettes',
        label: 'Recettes',
        items: [
            {
                label: 'Client facture / recette',
                slug: 'recette',
                permission: 'finance.view',
            },
            {
                label: 'Validation',
                slug: 'validation-recette',
                permission: 'finance.view',
            },
            {
                label: 'Recouvrement des créances',
                slug: 'recouvrement',
                permission: 'finance.view',
            },
        ],
    },
    {
        key: 'tresorerie',
        label: 'Trésorerie',
        items: [
            {
                label: 'Entrée de caisse',
                slug: 'entree-de-caisse',
                permission: 'finance.view',
            },
            {
                label: 'Bon à décaisser / à encaisser',
                slug: 'bons-caisse',
                permission: 'finance.view',
            },
            {
                label: 'Créances fournisseurs / B.C.',
                slug: 'creances-fournisseurs',
                permission: 'finance.view',
            },
            {
                label: 'Clôture de caisse',
                slug: 'cloture-de-caisse',
                permission: 'finance.view',
            },
            {
                label: 'Situation caisse',
                slug: 'situation-caisse',
                permission: 'finance.view',
            },
            {
                label: 'Engagements bancaires',
                slug: 'engagements-bancaires',
                permission: 'finance.view',
            },
        ],
    },
    {
        key: 'compta',
        label: 'Comptabilité générale',
        items: [
            {
                label: 'Comptabilité générale',
                slug: 'comptabilite-generale',
                permission: 'finance.view',
            },
            {
                label: 'Export recettes vers SAARI',
                slug: 'export-recettes-saari',
                permission: 'finance.view',
            },
            {
                label: 'Export charges vers SAARI',
                slug: 'export-charges-saari',
                permission: 'finance.view',
            },
            {
                label: 'Justifier une pièce de caisse',
                slug: 'justifier-piece-caisse',
                permission: 'finance.view',
            },
        ],
    },
];

export const fichierMenu: (LegacyLink | 'separator')[] = [
    {
        label: 'Exercice budgétaire (choix, ouverture, clôture)',
        slug: 'exercice-budgetaire',
        permission: 'budget.view',
    },
    {
        label: 'Paramètres / Élaboration budgétaire',
        slug: 'elaboration-budgetaire',
        permission: 'budget.view',
    },
    {
        label: "Reconduire le budget d'une année",
        slug: 'reconduire-budget',
        permission: 'budget.view',
    },
    'separator',
    {
        label: 'Valeurs réalisées',
        slug: 'valeurs-realisees',
        permission: 'budget.view',
    },
    {
        label: 'Valeurs réalisées détaillées',
        slug: 'valeurs-realisees-detaillees',
        permission: 'budget.view',
    },
    {
        label: 'Taux réalisé détaillé',
        slug: 'taux-realise-detaille',
        permission: 'budget.view',
    },
    {
        label: 'Utiliser les valeurs réalisées comme budget',
        slug: 'realise-comme-budget',
        permission: 'budget.view',
    },
    {
        label: 'Ratio rapprochement / écart',
        slug: 'ratio-ecart',
        permission: 'budget.view',
    },
    'separator',
    {
        label: 'Consultation du déboursé sec par coût',
        slug: 'debourse-par-cout',
        permission: 'budget.view',
    },
    {
        label: 'Consultation du déboursé sec par quantité',
        slug: 'debourse-par-quantite',
        permission: 'budget.view',
    },
    {
        label: 'Liste des notes de frais par projet',
        slug: 'notes-par-projet',
        permission: 'budget.view',
    },
    {
        label: 'Liste des commandes par fournisseur',
        slug: 'commandes-par-fournisseur',
        permission: 'achats.view',
    },
    {
        label: 'Situation des clients projets',
        slug: 'situation-clients-projets',
        permission: 'finance.view',
    },
    'separator',
    {
        label: 'Paramètres du fichier à importer',
        slug: 'parametres-import',
        permission: 'administration.view',
    },
    {
        label: 'Importation de données',
        slug: 'importation',
        permission: 'administration.view',
    },
    {
        label: 'Table des données importées',
        slug: 'donnees-importees',
        permission: 'administration.view',
    },
    {
        label: 'Valeurs des données importées',
        slug: 'valeurs-importees',
        permission: 'administration.view',
    },
    {
        label: 'Ratio rapprochement / écart des importées',
        slug: 'ratio-importees',
        permission: 'administration.view',
    },
    {
        label: 'Exportation de données',
        slug: 'exportation',
        permission: 'administration.view',
    },
    'separator',
    {
        label: 'Livre analytique des mouvements',
        slug: 'livre-analytique',
        permission: 'finance.view',
    },
    {
        label: 'Livre analytique des mouvements par produits',
        slug: 'livre-analytique-produits',
        permission: 'finance.view',
    },
    'separator',
    {
        label: 'Étapes de validation',
        slug: 'etapes-de-validation',
        permission: 'administration.view',
    },
];

export const topMenu: { label: string; href: string; permission: string }[] = [
    { label: 'Tableau de bord', href: '/dashboard', permission: 'achats.view' },
    {
        label: 'Tableau de bord mensuel',
        href: href('tableau-de-bord-mensuel'),
        permission: 'budget.view',
    },
    {
        label: 'Situation des projets',
        href: href('situation-des-projets'),
        permission: 'projets.view',
    },
    {
        label: 'Tableau de bord des projets',
        href: href('tableau-de-bord-des-projets'),
        permission: 'projets.view',
    },
];
