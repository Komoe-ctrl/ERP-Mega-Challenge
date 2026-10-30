/** Menus de l'application : un seul menu latéral (accordéon), le menu du haut et le menu Fichier. */
import {
    BookOpen,
    HandCoins,
    Handshake,
    HardHat,
    Receipt,
    Settings,
    ShoppingCart,
    Users,
    Wallet,
    Warehouse,
} from '@lucide/vue';
import type { Component } from 'vue';

export type LegacyLink = {
    label: string;
    slug: string;
    /** Permission minimale pour voir l'entrée (filtrage par rôle). */
    permission: string;
};

export type MenuItem = {
    label: string;
    href: string;
    /** Permission minimale pour voir l'entrée ; vide = toujours visible. */
    permission: string;
};

export type MenuGroup = {
    key: string;
    label: string;
    icon: Component;
    items: MenuItem[];
};

export const href = (slug: string): string => `/ecrans/${slug}`;

const e = (label: string, slug: string, permission: string): MenuItem => ({
    label,
    href: href(slug),
    permission,
});

/** Chaque écran n'apparaît qu'à un seul endroit. */
export const sidebarMenu: MenuGroup[] = [
    {
        key: 'projets',
        icon: HardHat,
        label: 'Projets et chantiers',
        items: [
            { label: 'Projets', href: '/projets', permission: 'projets.view' },
            {
                label: 'Chantiers',
                href: '/chantiers',
                permission: 'chantiers.view',
            },
            e('Planning', 'planning', 'chantiers.view'),
            {
                label: 'Budget chantier',
                href: '/budget',
                permission: 'budget.view',
            },
        ],
    },
    {
        key: 'da',
        icon: ShoppingCart,
        label: "Demandes d'achats (D.A.)",
        items: [
            {
                label: "Demande d'achat",
                href: '/achats/demandes',
                permission: 'achats.view',
            },
            {
                label: 'Cotations',
                href: '/achats/consultations',
                permission: 'achats.view',
            },
            {
                label: 'Bons de commande',
                href: '/achats/commandes',
                permission: 'achats.view',
            },
            {
                label: 'Bons de livraison',
                href: '/achats/receptions',
                permission: 'achats.view',
            },
            {
                label: 'Factures fournisseurs',
                href: '/achats/factures',
                permission: 'achats.view',
            },
            e(
                'Approbation des pièces engagées',
                'approbation-pieces-engagees',
                'achats.validate',
            ),
            {
                label: 'Validation',
                href: '/achats/a-valider',
                permission: 'achats.validate',
            },
            e('Suivi workflow', 'suivi-workflow-da', 'achats.view'),
            {
                label: 'Fournisseurs',
                href: '/fournisseurs',
                permission: 'fournisseurs.view',
            },
            {
                label: 'Référentiel des prix',
                href: '/achats/referentiel',
                permission: 'achats.view',
            },
        ],
    },
    {
        key: 'nf',
        icon: Receipt,
        label: 'Notes de frais',
        items: [
            e('Note de frais', 'note-de-frais', 'achats.view'),
            e('Validation', 'validation-nf', 'achats.validate'),
            e('Facture', 'facture-nf', 'achats.view'),
            e('Suivi workflow', 'suivi-workflow-nf', 'achats.view'),
        ],
    },
    {
        key: 'commercial',
        icon: Handshake,
        label: 'Commercial',
        items: [
            { label: 'Clients', href: '/clients', permission: 'clients.view' },
            { label: 'Devis', href: '/devis', permission: 'devis.view' },
            {
                label: 'Marchés et contrats',
                href: '/marches',
                permission: 'marches.view',
            },
        ],
    },
    {
        key: 'recettes',
        icon: HandCoins,
        label: 'Recettes',
        items: [
            {
                label: 'Situations de travaux',
                href: '/situations',
                permission: 'situations.view',
            },
            e('Client facture / recette', 'recette', 'finance.view'),
            e('Validation', 'validation-recette', 'finance.validate'),
            e('Recouvrement des créances', 'recouvrement', 'finance.view'),
        ],
    },
    {
        key: 'stocks',
        icon: Warehouse,
        label: 'Stocks',
        items: [
            {
                label: 'État des stocks',
                href: '/stocks',
                permission: 'stocks.view',
            },
            {
                label: 'Mouvements',
                href: '/stocks/mouvements',
                permission: 'stocks.view',
            },
            {
                label: 'Inventaire',
                href: '/stocks/inventaire',
                permission: 'stocks.view',
            },
        ],
    },
    {
        key: 'ressources',
        icon: Users,
        label: 'Ressources',
        items: [
            {
                label: 'Main-d’œuvre',
                href: '/main-oeuvre',
                permission: 'main_oeuvre.view',
            },
            { label: 'RH', href: '/rh', permission: 'rh.view' },
            { label: 'Engins', href: '/engins', permission: 'engins.view' },
        ],
    },
    {
        key: 'tresorerie',
        icon: Wallet,
        label: 'Trésorerie',
        items: [
            e('Entrée de caisse', 'entree-de-caisse', 'finance.view'),
            e('Bon à décaisser / à encaisser', 'bons-caisse', 'finance.view'),
            e(
                'Créances fournisseurs / B.C.',
                'creances-fournisseurs',
                'finance.view',
            ),
            e('Clôture de caisse', 'cloture-de-caisse', 'finance.view'),
            e('Situation caisse', 'situation-caisse', 'finance.view'),
            e('Engagements bancaires', 'engagements-bancaires', 'finance.view'),
        ],
    },
    {
        key: 'compta',
        icon: BookOpen,
        label: 'Comptabilité générale',
        items: [
            e('Comptabilité générale', 'comptabilite-generale', 'finance.view'),
            e(
                'Export recettes vers SAARI',
                'export-recettes-saari',
                'finance.view',
            ),
            e(
                'Export charges vers SAARI',
                'export-charges-saari',
                'finance.view',
            ),
            e(
                'Justifier une pièce de caisse',
                'justifier-piece-caisse',
                'finance.view',
            ),
        ],
    },
    {
        key: 'administration',
        icon: Settings,
        label: 'Administration',
        items: [
            {
                label: 'Utilisateurs',
                href: '/administration/users',
                permission: 'administration.view',
            },
            {
                label: 'Rôles',
                href: '/administration/roles',
                permission: 'administration.view',
            },
            e(
                'Étapes de validation',
                'etapes-de-validation',
                'administration.view',
            ),
            {
                label: 'Paramètres',
                href: '/administration/parametres',
                permission: 'administration.view',
            },
            {
                label: 'Journal des activités',
                href: '/administration/journal',
                permission: 'administration.view',
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
];

export const topMenu: { label: string; href: string; permission: string }[] = [
    { label: 'Tableau de bord', href: '/dashboard', permission: '' },
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
