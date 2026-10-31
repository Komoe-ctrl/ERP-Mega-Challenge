/** Types des pièces communes (note de frais, recette, pièce de caisse) reçus du serveur. */

export type PieceKindInfo = { value: string; label: string };

export type PieceStatusValue =
    'saisie' | 'en_validation' | 'approuvee' | 'rejetee';

export type PieceRow = {
    id: number;
    number: string;
    title: string;
    period: string;
    requester: string;
    bc_number: string | null;
    observation: string | null;
    status: PieceStatusValue;
    status_label: string;
    total: number;
    waiting: string | null;
    proof: string | null;
    invoice_number: string | null;
    reimbursed_at: string | null;
    can: {
        update: boolean;
        decide: boolean;
        button: string | null;
        reimburse: boolean;
    };
};

export type PieceLineInput = {
    designation: string;
    quantity: number;
    unit_price: number;
    rubrique_id: number | null;
};

export type PieceDetail = PieceRow & {
    project_id: number | null;
    project_lot_id: number | null;
    lot_component_id: number | null;
    lines: (PieceLineInput & { amount: number; rubrique: string })[];
    events: PieceEvent[];
};

export type PieceEvent = {
    at: string;
    by: string;
    action: string;
    step: string | null;
    comment: string | null;
};

export type Rubrique = { id: number; code: string; label: string };

export type ProjectTree = {
    id: number;
    name: string;
    lots: {
        id: number;
        name: string;
        components: { id: number; name: string }[];
    }[];
};

export type BudgetPanelData = {
    rubrique: Rubrique;
    budget: number;
    consumed: number;
    inProgress: number;
    used: number;
    remaining: number;
    rate: number;
    overrun: boolean;
};
