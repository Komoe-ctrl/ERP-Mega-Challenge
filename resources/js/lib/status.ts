export type Tone =
    'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'brand';

export type StatusMeta = { label: string; tone: Tone };

/** Classes Tailwind par ton : uniquement des tokens du thème (app.css). */
export const toneClasses: Record<Tone, string> = {
    neutral: 'bg-secondary text-secondary-foreground',
    info: 'bg-info-soft text-info',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    brand: 'bg-brand-soft text-brand-strong',
};

export const toneBar: Record<Tone, string> = {
    neutral: 'bg-muted-foreground',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    brand: 'bg-brand',
};

const m = (label: string, tone: Tone): StatusMeta => ({ label, tone });

/** Libellés et tons des statuts, par famille de document. */
export const statuses = {
    project: {
        etude: m('Étude', 'neutral'),
        devis: m('Devis', 'neutral'),
        appel_offres: m('Appel d’offres', 'info'),
        marche: m('Marché', 'info'),
        preparation: m('Préparation', 'brand'),
        en_cours: m('En cours', 'success'),
        reception: m('Réception', 'warning'),
        cloture: m('Clôturé', 'neutral'),
        abandonne: m('Abandonné', 'danger'),
    },
    site: {
        preparation: m('En préparation', 'brand'),
        en_cours: m('En cours', 'success'),
        suspendu: m('Suspendu', 'warning'),
        receptionne: m('Réceptionné', 'info'),
        cloture: m('Clôturé', 'neutral'),
    },
    request: {
        brouillon: m('Brouillon', 'neutral'),
        en_attente: m('En attente', 'warning'),
        validee: m('Validée', 'success'),
        refusee: m('Refusée', 'danger'),
        renvoyee: m('Renvoyée', 'brand'),
        annulee: m('Annulée', 'neutral'),
    },
    task: {
        done: m('Terminée', 'success'),
        late: m('En retard', 'danger'),
        risk: m('À risque', 'warning'),
        stale: m('Non renseignée', 'brand'),
        ok: m('Dans les temps', 'info'),
        upcoming: m('À venir', 'neutral'),
    },
    budget: {
        ok: m('Sous contrôle', 'success'),
        warning: m('Avertissement', 'warning'),
        critical: m('Alerte critique', 'danger'),
    },
    urgency: {
        normale: m('Normale', 'neutral'),
        urgente: m('Urgente', 'warning'),
        critique: m('Critique', 'danger'),
    },
    consultation: {
        ouverte: m('Ouverte', 'info'),
        comparee: m('Comparée', 'warning'),
        attribuee: m('Attribuée', 'success'),
    },
    order: {
        emis: m('Émis', 'info'),
        partiel: m('Partiellement reçu', 'warning'),
        receptionne: m('Réceptionné', 'success'),
        facture: m('Facturé', 'brand'),
        paye: m('Payé', 'neutral'),
    },
    reception: {
        conforme: m('Conforme', 'success'),
        reliquat: m('Reliquat', 'warning'),
        retour: m('Retour', 'danger'),
    },
    invoice: {
        a_controler: m('À contrôler', 'warning'),
        controlee: m('Contrôlée', 'success'),
        ecart: m('Écart de contrôle', 'danger'),
        payee: m('Payée', 'neutral'),
    },
    quote: {
        brouillon: m('Brouillon', 'neutral'),
        soumis: m('Soumis', 'warning'),
        valide: m('Validé', 'success'),
        refuse: m('Refusé', 'danger'),
    },
    situation: {
        brouillon: m('Brouillon', 'neutral'),
        validee: m('Validée', 'success'),
        payee: m('Payée', 'info'),
    },
    leave: {
        demandee: m('Demandée', 'warning'),
        validee: m('Validée', 'success'),
        refusee: m('Refusée', 'danger'),
    },
    equipment: {
        disponible: m('Disponible', 'success'),
        affecte: m('Affecté', 'info'),
        reparation: m('En réparation', 'danger'),
    },
    activity: {
        creation: m('Création', 'success'),
        modification: m('Modification', 'info'),
        validation: m('Validation', 'brand'),
        suppression: m('Suppression', 'danger'),
    },
} as const satisfies Record<string, Record<string, StatusMeta>>;

export type StatusKind = keyof typeof statuses;

export function statusMeta(kind: StatusKind, status: string): StatusMeta {
    const family = statuses[kind] as Record<string, StatusMeta>;

    return family[status] ?? { label: status, tone: 'neutral' };
}

/** Ton de la barre de progression selon la consommation d'un budget (RG10.3). */
export function consumptionTone(consumption: number): Tone {
    if (consumption >= 100) {
        return 'danger';
    }

    return consumption >= 80 ? 'warning' : 'success';
}
