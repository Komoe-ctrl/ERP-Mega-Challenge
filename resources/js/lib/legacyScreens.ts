import {
    bankCommitments,
    cashEntries,
    cashVouchers,
    engagedAccounts,
    generalLedger,
    importedDatasets,
    initialPieces,
    pieceTotal,
    projectTrees,
    receivables,
    rubriques,
    saariCharges,
    saariRecettes,
    supplierDebts,
} from '@/mocks/legacy';

/**
 * Écrans « liste + actions » de l'application actuelle, décrits par des données :
 * un seul composant (LegacyTable) les affiche tous, avec les libellés d'origine.
 */
export type ColType = 'text' | 'money' | 'date' | 'percent' | 'badge';

export type Col = { key: string; label: string; type?: ColType };

export type Row = Record<string, string | number | boolean | null>;

export type TableScreen = {
    title: string;
    description: string;
    columns: Col[];
    rows: Row[];
    /** Colonnes dont on affiche le total en pied de tableau. */
    totals?: string[];
    /** Boutons généraux (maquette : confirmation seulement). */
    actions?: string[];
    /** Action sur la ligne sélectionnée : modifie le champ `field`. */
    rowAction?: {
        label: string;
        field: string;
        value: string | boolean;
        done: string;
    };
    steps?: string[];
};

const ratio = (a: number, b: number): number => (b ? (a / b) * 100 : 0);

const realised = rubriques.map((r) => ({
    code: r.code,
    label: r.label,
    budget: r.budget,
    realise: r.consumed,
    committed: r.committed,
    rate: ratio(r.consumed, r.budget),
    gap: r.budget - r.consumed,
}));

const projectsOf = initialPieces.filter(
    (p) => p.kind === 'da' || p.kind === 'nf',
);

const noteTotals = projectTrees.map((t) => {
    const notes = initialPieces.filter(
        (p) => p.kind === 'nf' && p.project === t.project,
    );

    return {
        project: t.project,
        count: notes.length,
        amount: notes.reduce((s, p) => s + pieceTotal(p.lines), 0),
    };
});

export const tableScreens: Record<string, TableScreen> = {
    'approbation-pieces-engagees': {
        title: 'Approbation des comptes des pièces engagées',
        description:
            'Approuvez les engagements (bons de commande) imputés sur les rubriques budgétaires.',
        columns: [
            { key: 'number', label: 'Pièce engagée' },
            { key: 'rubrique', label: 'Rubrique' },
            { key: 'engaged', label: 'Montant engagé', type: 'money' },
            { key: 'approvedAt', label: 'Approuvée le', type: 'date' },
            { key: 'status', label: 'État', type: 'badge' },
        ],
        rows: engagedAccounts.map((e) => ({
            number: e.number,
            rubrique: e.rubrique,
            engaged: e.engaged,
            approvedAt: e.approvedAt,
            status: e.approvedAt ? 'Approuvée' : 'À approuver',
        })),
        totals: ['engaged'],
        rowAction: {
            label: 'Approuver',
            field: 'status',
            value: 'Approuvée',
            done: 'Engagement approuvé (maquette).',
        },
    },
    recouvrement: {
        title: 'Recouvrement des créances',
        description: 'Suivi des factures clients, relances et encaissements.',
        columns: [
            { key: 'client', label: 'Client' },
            { key: 'invoice', label: 'Facture' },
            { key: 'amount', label: 'Montant', type: 'money' },
            { key: 'collected', label: 'Encaissé', type: 'money' },
            { key: 'rest', label: 'Reste à recouvrer', type: 'money' },
            { key: 'dueDate', label: 'Échéance', type: 'date' },
            { key: 'reminders', label: 'Relances' },
        ],
        rows: receivables.map((r) => ({ ...r, rest: r.amount - r.collected })),
        totals: ['amount', 'collected', 'rest'],
        actions: ['Envoyer une relance', 'Enregistrer un encaissement'],
    },
    'bons-caisse': {
        title: 'Bon à décaisser / à encaisser',
        description: 'Bons en attente de traitement par la caisse.',
        columns: [
            { key: 'number', label: 'N° bon' },
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'label', label: 'Libellé' },
            { key: 'kind', label: 'Type', type: 'badge' },
            { key: 'amount', label: 'Montant', type: 'money' },
            { key: 'status', label: 'État', type: 'badge' },
        ],
        rows: cashVouchers.map((v) => ({
            ...v,
            kind: v.kind === 'decaisser' ? 'À décaisser' : 'À encaisser',
            status: v.status === 'traite' ? 'Traité' : 'À traiter',
        })),
        totals: ['amount'],
        actions: ['Nouveau bon'],
        rowAction: {
            label: 'Traiter le bon',
            field: 'status',
            value: 'Traité',
            done: 'Bon traité (maquette).',
        },
    },
    'creances-fournisseurs': {
        title: 'Créances fournisseurs / B.C.',
        description: 'Restes à payer par fournisseur et par bon de commande.',
        columns: [
            { key: 'supplier', label: 'Fournisseur' },
            { key: 'bcNumber', label: 'B.C.' },
            { key: 'amount', label: 'Montant', type: 'money' },
            { key: 'paid', label: 'Payé', type: 'money' },
            { key: 'rest', label: 'Reste à payer', type: 'money' },
            { key: 'dueDate', label: 'Échéance', type: 'date' },
        ],
        rows: supplierDebts.map((d) => ({ ...d, rest: d.amount - d.paid })),
        totals: ['amount', 'paid', 'rest'],
        actions: ['Enregistrer un paiement'],
    },
    'cloture-de-caisse': {
        title: 'Clôture de caisse',
        description:
            'Comparez le solde théorique au comptage physique avant de clôturer la journée.',
        columns: [
            { key: 'label', label: 'Ligne' },
            { key: 'amount', label: 'Montant', type: 'money' },
        ],
        rows: [
            { label: 'Solde d’ouverture', amount: 420_000 },
            {
                label: 'Total des entrées',
                amount: cashEntries
                    .filter((c) => c.kind === 'entree')
                    .reduce((s, c) => s + c.amount, 0),
            },
            {
                label: 'Total des sorties',
                amount: cashEntries
                    .filter((c) => c.kind === 'sortie')
                    .reduce((s, c) => s + c.amount, 0),
            },
            { label: 'Solde théorique', amount: 1_756_500 },
        ],
        actions: ['Saisir le comptage physique', 'Clôturer la caisse'],
    },
    'situation-caisse': {
        title: 'Situation caisse',
        description: 'Soldes et mouvements de la caisse par période.',
        columns: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'label', label: 'Libellé' },
            { key: 'in', label: 'Entrées', type: 'money' },
            { key: 'out', label: 'Sorties', type: 'money' },
        ],
        rows: cashEntries.map((c) => ({
            date: c.date,
            label: c.label,
            in: c.kind === 'entree' ? c.amount : 0,
            out: c.kind === 'sortie' ? c.amount : 0,
        })),
        totals: ['in', 'out'],
    },
    'engagements-bancaires': {
        title: 'Engagements bancaires',
        description: 'Cautions, crédits et lettres de change en cours.',
        columns: [
            { key: 'bank', label: 'Banque' },
            { key: 'label', label: 'Engagement' },
            { key: 'kind', label: 'Nature', type: 'badge' },
            { key: 'amount', label: 'Montant', type: 'money' },
            { key: 'dueDate', label: 'Échéance', type: 'date' },
        ],
        rows: bankCommitments.map((b) => ({
            ...b,
            kind:
                b.kind === 'caution'
                    ? 'Caution'
                    : b.kind === 'credit'
                      ? 'Crédit'
                      : 'Lettre de change',
        })),
        totals: ['amount'],
        actions: ['Nouvel engagement'],
    },
    'comptabilite-generale': {
        title: 'Comptabilité générale',
        description: 'Écritures comptables générées par les pièces approuvées.',
        columns: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'journal', label: 'Journal' },
            { key: 'account', label: 'Compte' },
            { key: 'label', label: 'Libellé' },
            { key: 'debit', label: 'Débit', type: 'money' },
            { key: 'credit', label: 'Crédit', type: 'money' },
        ],
        rows: generalLedger,
        totals: ['debit', 'credit'],
    },
    'export-recettes-saari': {
        title: 'Export recettes vers SAARI',
        description:
            'Aperçu des écritures de recettes qui seront transmises à SAARI.',
        columns: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'journal', label: 'Journal' },
            { key: 'account', label: 'Compte' },
            { key: 'label', label: 'Libellé' },
            { key: 'debit', label: 'Débit', type: 'money' },
            { key: 'credit', label: 'Crédit', type: 'money' },
        ],
        rows: saariRecettes,
        totals: ['debit', 'credit'],
        actions: ['Générer le fichier d’export'],
    },
    'export-charges-saari': {
        title: 'Export charges vers SAARI',
        description:
            'Aperçu des écritures de charges qui seront transmises à SAARI.',
        columns: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'journal', label: 'Journal' },
            { key: 'account', label: 'Compte' },
            { key: 'label', label: 'Libellé' },
            { key: 'debit', label: 'Débit', type: 'money' },
            { key: 'credit', label: 'Crédit', type: 'money' },
        ],
        rows: saariCharges,
        totals: ['debit', 'credit'],
        actions: ['Générer le fichier d’export'],
    },
    'justifier-piece-caisse': {
        title: 'Justifier une pièce de caisse',
        description:
            'Rattachez un justificatif aux sorties de caisse non justifiées.',
        columns: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'label', label: 'Libellé' },
            { key: 'amount', label: 'Montant', type: 'money' },
            { key: 'by', label: 'Saisi par' },
            { key: 'status', label: 'Justificatif', type: 'badge' },
        ],
        rows: cashEntries
            .filter((c) => c.kind === 'sortie')
            .map((c) => ({
                ...c,
                status: c.justified ? 'Justifiée' : 'À justifier',
            })),
        rowAction: {
            label: 'Joindre un justificatif',
            field: 'status',
            value: 'Justifiée',
            done: 'Justificatif joint (maquette).',
        },
    },
    'valeurs-realisees': {
        title: 'Valeurs réalisées',
        description: 'Montants réalisés par rubrique sur l’exercice.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'label', label: 'Libellé' },
            { key: 'budget', label: 'Budget', type: 'money' },
            { key: 'realise', label: 'Réalisé', type: 'money' },
        ],
        rows: realised,
        totals: ['budget', 'realise'],
    },
    'valeurs-realisees-detaillees': {
        title: 'Valeurs réalisées détaillées',
        description: 'Réalisé, engagé et reste par rubrique.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'label', label: 'Libellé' },
            { key: 'realise', label: 'Réalisé', type: 'money' },
            { key: 'committed', label: 'Engagé non réalisé', type: 'money' },
            { key: 'gap', label: 'Reste', type: 'money' },
        ],
        rows: realised,
        totals: ['realise', 'committed', 'gap'],
    },
    'taux-realise-detaille': {
        title: 'Taux réalisé détaillé',
        description:
            'Taux de réalisation de chaque rubrique par rapport au budget.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'label', label: 'Libellé' },
            { key: 'budget', label: 'Budget', type: 'money' },
            { key: 'rate', label: 'Taux réalisé', type: 'percent' },
        ],
        rows: realised,
    },
    'realise-comme-budget': {
        title: 'Utiliser les valeurs réalisées comme budget',
        description:
            'Reprenez le réalisé d’une année pour préremplir le budget de la suivante.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'label', label: 'Libellé' },
            { key: 'realise', label: 'Réalisé (base)', type: 'money' },
            { key: 'budget', label: 'Budget actuel', type: 'money' },
        ],
        rows: realised,
        totals: ['realise', 'budget'],
        actions: ['Appliquer comme budget'],
    },
    'ratio-ecart': {
        title: 'Ratio rapprochement / écart',
        description:
            'Écart entre le budget et le réalisé, rubrique par rubrique.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'budget', label: 'Budget', type: 'money' },
            { key: 'realise', label: 'Réalisé', type: 'money' },
            { key: 'gap', label: 'Écart', type: 'money' },
            { key: 'rate', label: 'Ratio', type: 'percent' },
        ],
        rows: realised,
        totals: ['budget', 'realise', 'gap'],
    },
    'debourse-par-cout': {
        title: 'Consultation du déboursé sec par coût',
        description: 'Déboursé sec cumulé par rubrique, en FCFA.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'label', label: 'Libellé' },
            { key: 'realise', label: 'Déboursé sec', type: 'money' },
        ],
        rows: realised,
        totals: ['realise'],
    },
    'debourse-par-quantite': {
        title: 'Consultation du déboursé sec par quantité',
        description: 'Quantités consommées par désignation.',
        columns: [
            { key: 'designation', label: 'Désignation' },
            { key: 'quantity', label: 'Quantité' },
            { key: 'rubrique', label: 'Rubrique' },
        ],
        rows: projectsOf.flatMap((p) =>
            p.lines.map((l) => ({
                designation: l.designation,
                quantity: l.quantity,
                rubrique: l.rubrique,
            })),
        ),
    },
    'notes-par-projet': {
        title: 'Liste des notes de frais par projet',
        description: 'Nombre et montant des notes de frais de chaque projet.',
        columns: [
            { key: 'project', label: 'Projet' },
            { key: 'count', label: 'Notes de frais' },
            { key: 'amount', label: 'Montant', type: 'money' },
        ],
        rows: noteTotals,
        totals: ['amount'],
    },
    'commandes-par-fournisseur': {
        title: 'Liste des commandes par fournisseur',
        description: 'Bons de commande regroupés par fournisseur.',
        columns: [
            { key: 'supplier', label: 'Fournisseur' },
            { key: 'bcNumber', label: 'B.C.' },
            { key: 'amount', label: 'Montant', type: 'money' },
        ],
        rows: supplierDebts.map((d) => ({
            supplier: d.supplier,
            bcNumber: d.bcNumber,
            amount: d.amount,
        })),
        totals: ['amount'],
    },
    'situation-clients-projets': {
        title: 'Situation des clients projets',
        description: 'Facturé, encaissé et reste à encaisser par client.',
        columns: [
            { key: 'client', label: 'Client' },
            { key: 'amount', label: 'Facturé', type: 'money' },
            { key: 'collected', label: 'Encaissé', type: 'money' },
            { key: 'rest', label: 'Reste', type: 'money' },
        ],
        rows: receivables.map((r) => ({
            client: r.client,
            amount: r.amount,
            collected: r.collected,
            rest: r.amount - r.collected,
        })),
        totals: ['amount', 'collected', 'rest'],
    },
    'parametres-import': {
        title: 'Paramètres du fichier à importer',
        description:
            'Correspondance entre les colonnes du fichier et les champs de l’ERP.',
        columns: [
            { key: 'column', label: 'Colonne du fichier' },
            { key: 'field', label: 'Champ de l’ERP' },
            { key: 'required', label: 'Obligatoire', type: 'badge' },
        ],
        rows: [
            { column: 'A', field: 'Date de pièce', required: 'Oui' },
            { column: 'B', field: 'Rubrique', required: 'Oui' },
            { column: 'C', field: 'Libellé', required: 'Non' },
            { column: 'D', field: 'Montant', required: 'Oui' },
        ],
        actions: ['Enregistrer les paramètres'],
    },
    importation: {
        title: 'Importation de données',
        description:
            'Chargez un fichier Excel ou CSV ; il sera contrôlé avant intégration.',
        columns: [
            { key: 'name', label: 'Fichier' },
            { key: 'importedAt', label: 'Importé le', type: 'date' },
            { key: 'rows', label: 'Lignes' },
            { key: 'mapped', label: 'État', type: 'badge' },
        ],
        rows: importedDatasets.map((d) => ({
            ...d,
            mapped: d.mapped ? 'Intégré' : 'À paramétrer',
        })),
        actions: ['Choisir un fichier à importer'],
    },
    'donnees-importees': {
        title: 'Table des données importées',
        description: 'Jeux de données déjà intégrés.',
        columns: [
            { key: 'name', label: 'Fichier' },
            { key: 'importedAt', label: 'Importé le', type: 'date' },
            { key: 'rows', label: 'Lignes' },
        ],
        rows: importedDatasets,
    },
    'valeurs-importees': {
        title: 'Valeurs des données importées',
        description: 'Valeurs importées par rubrique.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'label', label: 'Libellé' },
            { key: 'realise', label: 'Valeur importée', type: 'money' },
        ],
        rows: realised,
        totals: ['realise'],
    },
    'ratio-importees': {
        title: 'Ratio rapprochement / écart des importées',
        description:
            'Comparaison des valeurs importées avec le réalisé de l’ERP.',
        columns: [
            { key: 'code', label: 'Rubrique' },
            { key: 'budget', label: 'Réalisé ERP', type: 'money' },
            { key: 'realise', label: 'Valeur importée', type: 'money' },
            { key: 'gap', label: 'Écart', type: 'money' },
        ],
        rows: realised.map((r) => ({
            ...r,
            budget: r.realise + 120_000,
            gap: -120_000,
        })),
    },
    exportation: {
        title: 'Exportation de données',
        description: 'Exportez les données de l’exercice vers Excel ou CSV.',
        columns: [
            { key: 'name', label: 'Jeu de données' },
            { key: 'rows', label: 'Lignes' },
        ],
        rows: [
            { name: 'Pièces de l’exercice', rows: 312 },
            { name: 'Budget par rubrique', rows: 6 },
            { name: 'Mouvements de caisse', rows: 148 },
        ],
        actions: ['Exporter en Excel', 'Exporter en CSV'],
    },
    'livre-analytique': {
        title: 'Livre analytique des mouvements',
        description: 'Tous les mouvements classés par rubrique analytique.',
        columns: [
            { key: 'date', label: 'Date', type: 'date' },
            { key: 'account', label: 'Compte' },
            { key: 'label', label: 'Libellé' },
            { key: 'debit', label: 'Débit', type: 'money' },
            { key: 'credit', label: 'Crédit', type: 'money' },
        ],
        rows: generalLedger,
        totals: ['debit', 'credit'],
    },
    'livre-analytique-produits': {
        title: 'Livre analytique des mouvements par produits',
        description: 'Mouvements regroupés par produit / lot / section.',
        columns: [
            { key: 'project', label: 'Projet' },
            { key: 'product', label: 'Produit / lot / section' },
            { key: 'amount', label: 'Montant', type: 'money' },
        ],
        rows: initialPieces.map((p) => ({
            project: p.project,
            product: p.product,
            amount: pieceTotal(p.lines),
        })),
        totals: ['amount'],
    },
};
