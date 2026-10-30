<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { fcfa, fcfaShort } from '@/lib/format';

/**
 * Graphique en barres (Chart.js). Chart.js est enregistré module par module :
 * seuls les éléments utiles sont embarqués, pour garder des pages légères en 3G.
 *
 * Les couleurs viennent des tokens du thème (--chart-1 à --chart-5), une par
 * série ; les montants sont affichés en FCFA au survol et sur l'axe.
 * `unit` : 'fcfa' (valeurs en FCFA) ou 'mfcfa' (valeurs en millions de FCFA) ;
 * par défaut, 'mfcfa' si le libellé d'une série contient « M FCFA ».
 */
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps<{
    labels: string[];
    datasets: { label: string; data: number[] }[];
    unit?: 'fcfa' | 'mfcfa' | 'none';
}>();

const unit = computed(
    () =>
        props.unit ??
        (props.datasets.some((d) => d.label.includes('M FCFA'))
            ? 'mfcfa'
            : 'fcfa'),
);

const toFcfa = (v: number): number =>
    unit.value === 'mfcfa' ? v * 1_000_000 : v;

function token(name: string, fallback: string): string {
    if (typeof document === 'undefined') {
        return fallback;
    }

    return (
        getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim() || fallback
    );
}

const palette = (): string[] => [
    token('--color-brand', '#0057b5'),
    token('--color-cta', '#c2410c'),
    token('--chart-3', '#15803d'),
    token('--chart-4', '#b45309'),
    token('--chart-5', '#8a857d'),
];

const chartData = computed<ChartData<'bar'>>(() => {
    const colors = palette();

    return {
        labels: props.labels,
        datasets: props.datasets.map((d, i) => ({
            ...d,
            backgroundColor: colors[i % colors.length],
            borderRadius: 4,
        })),
    };
});

const options = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        tooltip: {
            callbacks: {
                label: (ctx) =>
                    unit.value === 'none'
                        ? `${ctx.dataset.label} : ${ctx.parsed.y}`
                        : `${ctx.dataset.label} : ${fcfa(toFcfa(Number(ctx.parsed.y)))}`,
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (v) =>
                    unit.value === 'none'
                        ? String(v)
                        : fcfaShort(toFcfa(Number(v))),
            },
        },
    },
}));
</script>

<template>
    <div class="relative h-64 w-full">
        <Bar :data="chartData" :options="options" />
    </div>
</template>
