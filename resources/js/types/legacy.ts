/** Types des écrans repris de l'application de bureau actuelle de Mega Challenge. */

export type LegacyService = {
    id: string;
    name: string;
    shortName: string;
    titles: string[];
};

export type PieceKind = 'da' | 'nf' | 'recette' | 'caisse';

/** Saisie → validation du chef de service → approbation du Directeur. */
export type PieceStatus = 'saisie' | 'validee' | 'approuvee' | 'rejetee';

export type WorkflowStep = {
    id: number;
    label: string;
    /** Titre (poste) habilité à franchir cette étape. */
    title: string;
    /** Libellé du bouton d'action, nommé d'après le niveau suivant. */
    button: string;
    enabled: boolean;
};

export type WorkflowEvent = {
    at: string;
    by: string;
    action: string;
    comment: string;
};

export type PieceLine = {
    designation: string;
    quantity: number;
    unitPrice: number;
    rubrique: string;
};

export type Piece = {
    id: number;
    kind: PieceKind;
    number: string;
    title: string;
    period: string;
    date: string;
    requester: string;
    bcNumber: string;
    observation: string;
    proof: string;
    project: string;
    product: string;
    component: string;
    status: PieceStatus;
    lines: PieceLine[];
    history: WorkflowEvent[];
};

export type Rubrique = {
    code: string;
    label: string;
    budget: number;
    consumed: number;
    committed: number;
};

export type ProjectTree = {
    project: string;
    products: { name: string; components: string[] }[];
};

export type CashEntry = {
    id: number;
    date: string;
    label: string;
    kind: 'entree' | 'sortie';
    amount: number;
    by: string;
    justified: boolean;
};

export type CashVoucher = {
    id: number;
    number: string;
    date: string;
    label: string;
    kind: 'decaisser' | 'encaisser';
    amount: number;
    status: 'a_traiter' | 'traite';
};

export type BankCommitment = {
    id: number;
    bank: string;
    label: string;
    amount: number;
    dueDate: string;
    kind: 'caution' | 'credit' | 'lettre_change';
};

export type SupplierDebt = {
    id: number;
    supplier: string;
    bcNumber: string;
    amount: number;
    paid: number;
    dueDate: string;
};

export type Receivable = {
    id: number;
    client: string;
    invoice: string;
    amount: number;
    collected: number;
    dueDate: string;
    reminders: number;
};

export type DeliveryNote = {
    id: number;
    number: string;
    bcNumber: string;
    supplier: string;
    date: string;
    lines: number;
    status: 'recu' | 'partiel' | 'attendu';
};

export type LegacyInvoice = {
    id: number;
    number: string;
    kind: 'achat' | 'note_frais';
    party: string;
    date: string;
    amount: number;
    status: 'a_payer' | 'payee';
};

export type Quotation = {
    id: number;
    number: string;
    daNumber: string;
    suppliers: { name: string; amount: number; delay: number }[];
    status: 'en_cours' | 'a_valider' | 'validee' | 'convertie';
};

export type EngagedAccount = {
    id: number;
    number: string;
    rubrique: string;
    engaged: number;
    approvedAt: string | null;
};

export type ExportRow = {
    id: number;
    date: string;
    journal: string;
    account: string;
    label: string;
    debit: number;
    credit: number;
};

export type ImportedDataset = {
    id: number;
    name: string;
    importedAt: string;
    rows: number;
    mapped: boolean;
};
