import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { TODAY } from '@/lib/format';

/**
 * Poste de travail de l'utilisateur connecté. Le service et le titre viennent
 * du compte (ses rôles) : on ne les choisit plus à la connexion. Un compte qui
 * occupe plusieurs postes peut basculer de l'un à l'autre depuis l'en-tête.
 * Maquette : l'exercice, le poste actif et le pointage sont gardés dans le navigateur.
 */
export type Poste = { serviceId: string; title: string };

/** Postes occupés par rôle (service, titre) — à remplacer par les données du compte. */
const postesByRole: Record<string, Poste[]> = {
    administrateur: [
        {
            serviceId: 'admin',
            title: 'Chargé de l’administration et du contrôle',
        },
    ],
    direction_generale: [
        { serviceId: 'pdg', title: 'Président Directeur Général' },
        { serviceId: 'btp', title: 'Directeur Mega Challenge' },
    ],
    direction_technique: [
        { serviceId: 'btp', title: 'SG1 – Chargé des projets immobiliers' },
        { serviceId: 'btp', title: 'Directeur Mega Challenge' },
    ],
    service_achats_btp: [{ serviceId: 'btp', title: 'Technicien 1' }],
    conducteur_travaux: [
        { serviceId: 'btp', title: 'Technicien 2' },
        { serviceId: 'btp', title: 'Technicien 1' },
    ],
    chef_chantier: [{ serviceId: 'btp', title: 'Technicien 2' }],
    magasinier: [{ serviceId: 'btp', title: 'Assistante SG1' }],
    comptabilite: [
        {
            serviceId: 'admin',
            title: 'Chargé de l’administration et du contrôle',
        },
    ],
    finance: [
        {
            serviceId: 'admin',
            title: 'Chargé de l’administration et du contrôle',
        },
    ],
    ressources_humaines: [
        {
            serviceId: 'admin',
            title: 'Chargé de l’administration et du contrôle',
        },
    ],
};

const fallback: Poste = { serviceId: 'btp', title: 'Technicien 1' };

type State = { exercice: number; posteIndex: number; arrivedAt: string | null };

const KEY = 'erp.legacy.session';
const defaults: State = { exercice: 2026, posteIndex: 0, arrivedAt: null };

function load(): State {
    try {
        const raw = localStorage.getItem(KEY);

        return raw ? { ...defaults, ...JSON.parse(raw) } : { ...defaults };
    } catch {
        return { ...defaults };
    }
}

const state = ref<State>(
    typeof localStorage === 'undefined' ? { ...defaults } : load(),
);

watch(
    state,
    (value) => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(KEY, JSON.stringify(value));
        }
    },
    { deep: true },
);

export function useLegacySession() {
    const page = usePage();

    const postes = computed<Poste[]>(() => {
        const found = page.props.auth.roles.flatMap(
            (r) => postesByRole[r] ?? [],
        );

        return found.length ? found : [fallback];
    });
    const poste = computed<Poste>(
        () => postes.value[state.value.posteIndex] ?? postes.value[0],
    );
    const hasArrivedToday = computed(
        () => state.value.arrivedAt?.slice(0, 10) === TODAY,
    );

    function clockIn() {
        state.value.arrivedAt = `${TODAY}T08:02:00`;
    }

    return { session: state, postes, poste, hasArrivedToday, clockIn };
}
