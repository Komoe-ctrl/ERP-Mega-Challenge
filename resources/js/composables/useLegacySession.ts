import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { TODAY } from '@/lib/format';

/**
 * Poste de travail de l'utilisateur connecté : le titre et le service viennent
 * du compte (partagés par le serveur dans `auth.poste`). L'exercice en cours et
 * le pointage d'arrivée sont gardés dans le navigateur (maquette).
 */
type State = { exercice: number; arrivedAt: string | null };

const KEY = 'erp.legacy.session';
const defaults: State = { exercice: 2026, arrivedAt: null };

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
        () => page.props.auth.poste ?? { title: '—', service: '—' },
    );
    const hasArrivedToday = computed(
        () => state.value.arrivedAt?.slice(0, 10) === TODAY,
    );

    function clockIn() {
        state.value.arrivedAt = `${TODAY}T08:02:00`;
    }

    return { session: state, poste, hasArrivedToday, clockIn };
}
