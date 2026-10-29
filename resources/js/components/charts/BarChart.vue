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

/**
 * Graphique en barres (Chart.js). Chart.js est enregistré module par module :
 * seuls les éléments utiles sont embarqués, pour garder des pages légères en 3G.
 */
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps<{
    labels: string[];
    datasets: { label: string; data: number[] }[];
}>();

const chartData = computed<ChartData<'bar'>>(() => ({
    labels: props.labels,
    datasets: props.datasets,
}));

const options: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    scales: { y: { beginAtZero: true } },
};
</script>

<template>
    <div class="relative h-64 w-full">
        <Bar :data="chartData" :options="options" />
    </div>
</template>
