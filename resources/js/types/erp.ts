/**
 * Types métier de l'ERP, alignés sur les futurs modèles (docs/frontend-notes.md).
 * Montants en FCFA : entiers. Quantités : nombres décimaux. Dates : ISO AAAA-MM-JJ.
 */

export type Id = number;

/* ---------- Projets (M4) ---------- */

export type ProjectStatus =
    | 'etude'
    | 'devis'
    | 'appel_offres'
    | 'marche'
    | 'preparation'
    | 'en_cours'
    | 'reception'
    | 'cloture'
    | 'abandonne';

export type Project = {
    id: Id;
    number: string;
    name: string;
    clientId: Id | null;
    clientName: string;
    location: string;
    managerId?: Id;
    manager: string;
    agencyId?: Id;
    agency: string;
    budget: number;
    startDate: string;
    endDate: string;
    status: ProjectStatus;
    abandonReason?: string | null;
    /** Présents sur la fiche du projet uniquement. */
    documents?: { name: string; size: string; date: string }[];
};

/* ---------- Chantiers (M7) ---------- */

export type SiteStatus =
    'preparation' | 'en_cours' | 'suspendu' | 'receptionne' | 'cloture';

export type Chantier = {
    id: Id;
    code: string;
    name: string;
    projectId: Id;
    projectName: string;
    location: string;
    startDate: string;
    endDate: string;
    status: SiteStatus;
    manager: string; // conducteur de travaux
    foreman: string; // chef de chantier
    team: { name: string; role: string }[];
    lastPhoto: string[];
};

export type TaskHealth = 'done' | 'late' | 'risk' | 'stale' | 'ok' | 'upcoming';

export type Task = {
    id: Id;
    chantierId: Id;
    name: string;
    start: string;
    end: string;
    progress: number; // pourcentage réel
    weight: number; // pondération pour l'avancement global
    milestone?: boolean;
    lastUpdate: string;
    comment?: string | null;
    /** Calculés par le serveur (RG7.5 à RG7.7), jamais par l'écran. */
    theoretical?: number;
    health?: TaskHealth;
};

export type DailyReport = {
    id: Id;
    chantierId: Id;
    date: string;
    author: string;
    works: string;
    headcount: number;
    materialsReceived: string;
    materialsUsed: string;
    incidents: string;
    photos: number;
};

/* ---------- Achats (M8) ---------- */

export type Urgency = 'normale' | 'urgente' | 'critique';

export type RequestStatus =
    'brouillon' | 'en_attente' | 'validee' | 'refusee' | 'renvoyee' | 'annulee';

export type Article = {
    id: Id;
    reference: string;
    label: string;
    category: string;
    unit: string;
    price: number;
    active: boolean;
    updatedAt: string;
    history?: { date: string; price: number; by: string; reason: string }[];
};

export type RequestLine = {
    id: Id;
    articleId: Id;
    quantity: number;
    /** Quantité ajustée par le valideur (RG8.5), l'écart est tracé. */
    adjustedQuantity?: number;
    needDate: string;
    urgency: Urgency;
};

export type RequestEvent = {
    date: string;
    actor: string;
    action: string;
    reason?: string;
};

export type PurchaseRequest = {
    id: Id;
    number: string;
    chantierId: Id;
    chantierName: string;
    requester: string;
    createdAt: string;
    status: RequestStatus;
    justification: string;
    lines: RequestLine[];
    /** Prix figés à la validation (RG8.6) : articleId -> prix. */
    frozenPrices?: Record<number, number>;
    needsDirection: boolean;
    history: RequestEvent[];
};

export type MissingArticleReport = {
    id: Id;
    label: string;
    requester: string;
    chantier: string;
    date: string;
};

/* ---------- Budget (M10) ---------- */

export type BudgetNature = 'materiaux' | 'main_oeuvre' | 'materiel';

export type BudgetLine = {
    id: Id;
    chantierId: Id;
    post: string; // poste du déboursé sec (gros œuvre, second œuvre...)
    nature: BudgetNature;
    initial: number;
    revised: number;
    committed: number; // engagé
    actual: number; // coût réel
};

export type BudgetRevision = {
    date: string;
    chantierId: Id;
    post: string;
    amount: number;
    reason: string;
    author: string;
    validatedBy: string;
};

/* ---------- Transverse ---------- */

export type NotificationKind =
    | 'demande'
    | 'decision'
    | 'budget'
    | 'planning'
    | 'stock'
    | 'engin'
    | 'echeance';

export type AppNotification = {
    id: Id;
    kind: NotificationKind;
    title: string;
    body: string;
    date: string;
    read: boolean;
    href: string;
};

export type ActivityEntry = {
    id: Id;
    date: string;
    actor: string;
    event: 'creation' | 'modification' | 'validation' | 'suppression';
    subject: string;
    changes: { field: string; before: string; after: string }[];
};

export type Agency = {
    id: Id;
    code: string;
    name: string;
    city: string;
    active: boolean;
};
export type Service = {
    id: Id;
    code: string;
    name: string;
    users: number;
    active: boolean;
};
export type SiteRecord = {
    id: Id;
    code: string;
    name: string;
    agency: string;
    type: 'bureau' | 'depot_central' | 'magasin_chantier';
    active: boolean;
};

export type SettingGroup = {
    group: string;
    items: {
        key: string;
        label: string;
        value: string;
        unit?: string;
        description?: string;
    }[];
};

/* ---------- Fournisseurs et achats V2 (M3, M8) ---------- */

export type Supplier = {
    id: Id;
    name: string;
    categories: string[];
    contact: string;
    phone: string;
    city: string;
    rating: number; // note moyenne sur 5
    openOrders: number;
    unpaidInvoices: number;
};

export type Quote = {
    supplierId: Id;
    supplierName: string;
    unitTotal: number;
    delayDays: number;
    selected?: boolean;
};

export type Consultation = {
    id: Id;
    number: string;
    requestNumber: string;
    chantierName: string;
    status: 'ouverte' | 'comparee' | 'attribuee';
    items: string;
    quotes: Quote[];
    minSuppliers: number;
};

export type PurchaseOrderStatus =
    'emis' | 'partiel' | 'receptionne' | 'facture' | 'paye';

export type PurchaseOrder = {
    id: Id;
    number: string;
    supplierName: string;
    chantierName: string;
    requestNumbers: string[];
    date: string;
    amount: number;
    status: PurchaseOrderStatus;
    lines: {
        label: string;
        unit: string;
        ordered: number;
        received: number;
        unitPrice: number;
    }[];
};

export type Reception = {
    id: Id;
    number: string;
    orderNumber: string;
    supplierName: string;
    date: string;
    lines: { label: string; ordered: number; received: number; unit: string }[];
    gap: 'conforme' | 'reliquat' | 'retour';
};

export type SupplierInvoice = {
    id: Id;
    number: string;
    supplierName: string;
    orderNumber: string;
    receptionNumber: string;
    date: string;
    dueDate: string;
    amount: number;
    match: { order: boolean; reception: boolean; price: boolean };
    status: 'a_controler' | 'controlee' | 'ecart' | 'payee';
};

/* ---------- Stocks (M9) ---------- */

export type StockItem = {
    id: Id;
    reference: string;
    label: string;
    family: string;
    unit: string;
    warehouse: string;
    quantity: number;
    minimum: number;
    averageCost: number; // coût moyen pondéré (RG9.5)
};

export type StockMovement = {
    id: Id;
    date: string;
    type:
        | 'entree'
        | 'sortie'
        | 'transfert'
        | 'inventaire'
        | 'retour'
        | 'perte'
        | 'ajustement';
    label: string;
    quantity: number;
    unit: string;
    from: string;
    to: string;
    chantier?: string;
    author: string;
    reason?: string;
};

export type InventoryLine = {
    id: Id;
    label: string;
    unit: string;
    theoretical: number;
    counted: number;
};

/* ---------- Commercial V3 (M2, M5, M6, M11) ---------- */

export type ClientRecord = {
    id: Id;
    number: string;
    name: string;
    type:
        | 'particulier'
        | 'entreprise'
        | 'promoteur'
        | 'administration'
        | 'institution'
        | 'societe_privee';
    contact: string;
    phone: string;
    city: string;
    projectsInProgress: number;
    invoiced: number;
    paid: number;
};

export type QuoteStatus = 'brouillon' | 'soumis' | 'valide' | 'refuse';

export type WorkItem = {
    id: Id;
    label: string;
    unit: string;
    quantity: number;
    unitPrice: number;
    components: string;
};

export type QuoteDoc = {
    id: Id;
    number: string;
    version: number;
    clientName: string;
    projectName: string;
    date: string;
    status: QuoteStatus;
    discountRate: number;
    vatRate: number;
    directCost: number; // déboursé sec
    works: WorkItem[];
};

export type Contract = {
    id: Id;
    number: string;
    clientName: string;
    projectName: string;
    initialAmount: number;
    signedAt: string;
    deadlineDays: number;
    advanceRate: number;
    retentionRate: number;
    paymentDays: number;
    amendments: {
        number: string;
        date: string;
        label: string;
        amount: number;
        days: number;
        validated: boolean;
    }[];
};

export type Situation = {
    id: Id;
    contractNumber: string;
    clientName: string;
    number: number;
    date: string;
    progressCumul: number; // avancement cumulé en %
    previousProgress: number;
    marketAmount: number;
    status: 'brouillon' | 'validee' | 'payee';
    retentionRate: number;
    advanceRefundRate: number;
    vatRate: number;
};

/* ---------- Ressources V4 (M12 à M14) ---------- */

export type Employee = {
    id: Id;
    matricule: string;
    name: string;
    category:
        'ouvrier' | 'technicien' | 'chef_equipe' | 'ingenieur' | 'conducteur';
    job: string;
    contract: 'CDI' | 'CDD' | 'Journalier';
    hourlyCost: number;
    baseSalary: number;
    chantier: string;
    onLeave: boolean;
};

export type Leave = {
    id: Id;
    employee: string;
    type: string;
    from: string;
    to: string;
    status: 'demandee' | 'validee' | 'refusee';
};

export type Advance = {
    id: Id;
    employee: string;
    date: string;
    amount: number;
    deducted: number;
};

export type Equipment = {
    id: Id;
    code: string;
    name: string;
    type: string;
    chantier: string;
    status: 'disponible' | 'affecte' | 'reparation';
    hours: number;
    fuelLiters: number;
    repairCost: number;
    nextMaintenance: string;
};

export type CashEntry = {
    id: Id;
    date: string;
    label: string;
    kind: 'recette' | 'depense';
    amount: number;
    chantier?: string;
    receipt: string;
    account: 'caisse' | 'banque';
};

export type ChantierProfit = {
    chantierName: string;
    marketAmount: number;
    invoiced: number;
    cashed: number;
    cost: { materiaux: number; main_oeuvre: number; materiel: number };
};
