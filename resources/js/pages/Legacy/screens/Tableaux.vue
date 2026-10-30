<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { dateFr, fcfa, percent } from '@/lib/format';
import { monthlyFigures, rubriques } from '@/mocks/legacy';
import { projects } from '@/mocks/chantiers';

const props = defineProps<{ mode: 'mensuel' | 'situation' | 'projets' }>();

const titles = {
    mensuel: [
        'Tableau de bord mensuel',
        'Budget et réalisé mois par mois, en millions de FCFA.',
    ],
    situation: [
        'Situation des projets',
        'État, budget et échéance de chaque projet.',
    ],
    projets: [
        'Tableau de bord des projets',
        'Indicateurs de synthèse du portefeuille de projets.',
    ],
} as const;

const budget = computed(() => rubriques.reduce((s, r) => s + r.budget, 0));
const consumed = computed(() => rubriques.reduce((s, r) => s + r.consumed, 0));
const committed = computed(() =>
    rubriques.reduce((s, r) => s + r.committed, 0),
);

const portfolio = computed(() => projects.reduce((s, p) => s + p.budget, 0));
const active = computed(
    () => projects.filter((p) => p.status === 'en_cours').length,
);
</script>

<template>
    <div class="space-y-4 p-4">
        <PageHeader
            :title="titles[props.mode][0]"
            :description="titles[props.mode][1]"
        />

        <template v-if="props.mode === 'mensuel'">
            <div class="grid gap-3 sm:grid-cols-3">
                <KpiCard label="Budget de l’exercice" :value="fcfa(budget)" />
                <KpiCard
                    label="Réalisé"
                    :value="fcfa(consumed)"
                    :hint="percent((consumed / budget) * 100, 1)"
                />
                <KpiCard label="Engagé non réalisé" :value="fcfa(committed)" />
            </div>
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <BarChart
                    :labels="monthlyFigures.map((m) => m.month)"
                    :datasets="[
                        {
                            label: 'Budget (M FCFA)',
                            data: monthlyFigures.map((m) => m.budget),
                        },
                        {
                            label: 'Réalisé (M FCFA)',
                            data: monthlyFigures.map((m) => m.realise),
                        },
                    ]"
                />
            </section>
        </template>

        <div
            v-else-if="props.mode === 'situation'"
            class="overflow-x-auto rounded-xl border bg-card shadow-xs"
        >
            <table class="w-full min-w-[40rem] text-sm">
                <thead
                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">Projet</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">État</th>
                        <th class="px-3 py-2 text-right">Budget</th>
                        <th class="px-3 py-2">Fin prévue</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in projects"
                        :key="p.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-3 py-2 font-medium">
                            <a
                                :href="`/projets/${p.id}`"
                                class="hover:underline"
                                >{{ p.name }}</a
                            >
                        </td>
                        <td class="px-3 py-2">{{ p.clientName }}</td>
                        <td class="px-3 py-2">
                            <StatusBadge kind="project" :status="p.status" />
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ fcfa(p.budget) }}
                        </td>
                        <td class="px-3 py-2">{{ dateFr(p.endDate) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template v-else>
            <div class="grid gap-3 sm:grid-cols-3">
                <KpiCard label="Projets" :value="String(projects.length)" />
                <KpiCard label="Projets en cours" :value="String(active)" />
                <KpiCard label="Portefeuille" :value="fcfa(portfolio)" />
            </div>
            <section class="rounded-xl border bg-card p-4 shadow-xs">
                <BarChart
                    :labels="projects.map((p) => p.number)"
                    :datasets="[
                        {
                            label: 'Budget (M FCFA)',
                            data: projects.map((p) =>
                                Math.round(p.budget / 1_000_000),
                            ),
                        },
                    ]"
                />
            </section>
        </template>
    </div>
</template>
