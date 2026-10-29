<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { dateFr, fcfa, number } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import {
    budgetLevel,
    budgetOf,
    budgetRevisions,
    budgetTotals,
} from '@/mocks/achats';
import { chantiers } from '@/mocks/chantiers';
import type { BudgetLine } from '@/types/erp';

const props = defineProps<{ id: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Budget', href: '/budget' }] },
});

const chantier = computed(
    () => chantiers.find((c) => c.id === props.id) ?? chantiers[0],
);
const lines = computed(() => budgetOf(chantier.value.id));
const totals = computed(() => budgetTotals(lines.value));
const level = computed(() => budgetLevel(totals.value.consumption));
const revisions = computed(() =>
    budgetRevisions.filter((r) => r.chantierId === chantier.value.id),
);
const lineConsumption = (l: BudgetLine) =>
    l.revised ? ((l.committed + l.actual) / l.revised) * 100 : 0;
const natureLabel = {
    materiaux: 'Matériaux',
    main_oeuvre: 'Main-d’œuvre',
    materiel: 'Matériel',
} as const;

const chartLabels = computed(() => lines.value.map((l) => l.post));
const chartSets = computed(() => [
    {
        label: 'Révisé (M FCFA)',
        data: lines.value.map((l) => Math.round(l.revised / 1_000_000)),
    },
    {
        label: 'Engagé + réel (M FCFA)',
        data: lines.value.map((l) =>
            Math.round((l.committed + l.actual) / 1_000_000),
        ),
    },
]);
</script>

<template>
    <Head :title="`Budget — ${chantier.name}`" />

    <div class="space-y-5 p-4">
        <PageHeader
            :title="`Budget — ${chantier.name}`"
            :description="chantier.code"
        >
            <StatusBadge kind="budget" :status="level" />
            <Button variant="outline" as-child
                ><Link href="/budget">Tous les chantiers</Link></Button
            >
            <Button variant="outline" as-child
                ><Link :href="`/chantiers/${chantier.id}`"
                    >Fiche chantier</Link
                ></Button
            >
        </PageHeader>

        <p
            v-if="level !== 'ok'"
            class="rounded-lg p-3 text-sm"
            :class="
                level === 'critical'
                    ? 'bg-danger-soft text-danger'
                    : 'bg-warning-soft text-warning'
            "
        >
            {{
                level === 'critical'
                    ? 'Alerte critique : le budget révisé est entièrement consommé.'
                    : 'Avertissement : plus de 80 % du budget révisé est consommé.'
            }}
            Le conducteur de travaux et la Direction technique sont notifiés.
        </p>

        <div v-if="lines.length" class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <KpiCard
                    label="Budget initial (DS)"
                    :value="fcfa(totals.initial)"
                />
                <KpiCard label="Budget révisé" :value="fcfa(totals.revised)" />
                <KpiCard
                    label="Engagé"
                    :value="fcfa(totals.committed)"
                    hint="Demandes validées, non reçues"
                />
                <KpiCard label="Coût réel" :value="fcfa(totals.actual)" />
                <KpiCard
                    label="Reste à engager"
                    :value="fcfa(totals.remaining)"
                    :tone="totals.remaining < 0 ? 'danger' : 'success'"
                />
            </div>

            <section class="space-y-2 rounded-xl border bg-card p-4 shadow-xs">
                <ProgressBar
                    :value="totals.consumption"
                    :tone="consumptionTone(totals.consumption)"
                    :label="`Consommation du budget révisé : ${number(totals.consumption, 1)} %`"
                    :show-value="false"
                />
                <p class="text-xs text-muted-foreground">
                    Seuils : avertissement 80 % · alerte critique 100 %.
                </p>
            </section>

            <section
                class="overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 font-medium">Poste</th>
                                <th class="p-3 font-medium">Nature</th>
                                <th class="p-3 text-right font-medium">
                                    Initial
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Révisé
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Engagé
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Coût réel
                                </th>
                                <th class="p-3 text-right font-medium">
                                    Reste
                                </th>
                                <th class="p-3 font-medium">Consommation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in lines" :key="l.id" class="border-t">
                                <td class="p-3 font-medium">{{ l.post }}</td>
                                <td class="p-3">{{ natureLabel[l.nature] }}</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.initial) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.revised) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.committed) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(l.actual) }}
                                </td>
                                <td
                                    class="p-3 text-right tabular-nums"
                                    :class="
                                        l.revised - l.committed - l.actual < 0
                                            ? 'text-danger'
                                            : ''
                                    "
                                >
                                    {{
                                        fcfa(l.revised - l.committed - l.actual)
                                    }}
                                </td>
                                <td class="min-w-36 p-3">
                                    <ProgressBar
                                        :value="lineConsumption(l)"
                                        :tone="
                                            consumptionTone(lineConsumption(l))
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td class="p-3" colspan="2">Total</td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.initial) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.revised) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.committed) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.actual) }}
                                </td>
                                <td class="p-3 text-right tabular-nums">
                                    {{ fcfa(totals.remaining) }}
                                </td>
                                <td class="p-3 tabular-nums">
                                    {{ number(totals.consumption, 1) }} %
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Révisé et consommé par poste
                    </h2>
                    <BarChart :labels="chartLabels" :datasets="chartSets" />
                </section>
                <section class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 class="mb-3 font-display font-semibold">
                        Révisions du budget
                    </h2>
                    <ul v-if="revisions.length" class="divide-y text-sm">
                        <li
                            v-for="r in revisions"
                            :key="r.date + r.post"
                            class="py-2.5"
                        >
                            <p class="font-medium">
                                {{ r.post }} : +{{ fcfa(r.amount) }}
                            </p>
                            <p class="text-muted-foreground">
                                « {{ r.reason }} »
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ dateFr(r.date) }} · {{ r.author }} · validé
                                par {{ r.validatedBy }}
                            </p>
                        </li>
                    </ul>
                    <EmptyState v-else compact title="Aucune révision" />
                </section>
            </div>
        </div>
        <EmptyState v-else title="Budget non renseigné pour ce chantier" />
    </div>
</template>
