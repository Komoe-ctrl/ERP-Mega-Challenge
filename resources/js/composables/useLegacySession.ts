import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * Poste de travail de l'utilisateur connecté : le titre et le service viennent
 * du compte (`auth.poste`), le pointage du jour est enregistré côté serveur
 * (`attendance`). Seul l'exercice en cours est gardé dans le navigateur.
 */
type State = { exercice: number };

const KEY = 'erp.legacy.session';
const defaults: State = { exercice: 2026 };

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

    const poste = computed(
        () => page.props.auth.poste ?? { id: null, title: '—', service: '—' },
    );
    const postes = computed(() => page.props.auth.postes ?? []);
    const arrivedAt = computed(() => page.props.attendance?.arrivedAt ?? null);
    const hasArrivedToday = computed(() => arrivedAt.value !== null);

    /** Enregistre le pointage d'arrivée de l'utilisateur authentifié. */
    function clockIn() {
        router.post('/pointage', {}, { preserveScroll: true });
    }

    /** Change le poste actif de la session (utilisateur ayant plusieurs postes). */
    function choosePoste(id: number | null) {
        if (id !== null) {
            router.put('/poste', { poste_id: id }, { preserveScroll: true });
        }
    }

    return {
        session: state,
        poste,
        postes,
        arrivedAt,
        hasArrivedToday,
        clockIn,
        choosePoste,
    };
}
