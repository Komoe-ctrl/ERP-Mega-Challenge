import { ref, watch } from 'vue';

/**
 * Session « poste de travail » de l'application actuelle : service, titre,
 * exercice en cours et pointage d'arrivée. Maquette : stockée dans le navigateur.
 * Le compte reste personnel ; le titre n'est qu'un poste affiché.
 */
type Session = {
    serviceId: string;
    title: string;
    exercice: number;
    arrivedAt: string | null;
};

const KEY = 'erp.legacy.session';

const defaults: Session = {
    serviceId: 'btp',
    title: 'Technicien 1',
    exercice: 2026,
    arrivedAt: null,
};

function load(): Session {
    try {
        const raw = localStorage.getItem(KEY);

        return raw ? { ...defaults, ...JSON.parse(raw) } : { ...defaults };
    } catch {
        return { ...defaults };
    }
}

const session = ref<Session>(
    typeof localStorage === 'undefined' ? { ...defaults } : load(),
);

watch(
    session,
    (value) => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(KEY, JSON.stringify(value));
        }
    },
    { deep: true },
);

export function useLegacySession() {
    return { session };
}
