<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { fcfa, percent } from '@/lib/format';
import { financeNav } from '@/lib/navs';
import { profitability } from '@/mocks/v4';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Comptabilité générale',
                href: '/ecrans/comptabilite-generale',
            },
            { title: 'Rentabilité', href: '/finance/rentabilite' },
        ],
    },
});

// CA15 : montant du marché, travaux facturés, encaissements, coût réel par nature, marge réelle.
// Marge réelle = cumul des travaux réalisés (facturés) - coût réel (M10).
const rows = computed(() =>
    profitability.map((p) => {
        const cost = p.cost.materiaux + p.cost.main_oeuvre + p.cost.materiel;
        const margin = p.invoiced - cost;

        return {
            ...p,
            totalCost: cost,
            margin,
            rate: p.invoiced ? (margin / p.invoiced) * 100 : 0,
        };
    }),
);

const labels = computed(() => rows.value.map((r) => r.chantierName));
const sets = computed(() => [
    {
        label: 'Facturé (M FCFA)',
        data: rows.value.map((r) => Math.round(r.invoiced / 1_000_000)),
    },
    {
        label: 'Coût réel (M FCFA)',
        data: rows.value.map((r) => Math.round(r.totalCost / 1_000_000)),
    },
]);
</script>

<template>
    <Head title="Rentabilité par chantier" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Rentabilité par chantier"
            description="Marché, facturation, encaissements, coût réel par nature et marge réelle."
        />
        <SubNav :links="financeNav" />

        <div class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="r in rows"
                :key="r.chantierName"
                class="space-y-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <h2 class="font-display font-semibold">{{ r.chantierName }}</h2>
                <dl class="space-y-1.5 text-sm tabular-nums">
                    <div class="flex justify-between">
                        <dt>Montant du marché</dt>
                        <dd>{{ fcfa(r.marketAmount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Travaux facturés</dt>
                        <dd>{{ fcfa(r.invoiced) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Encaissements</dt>
                        <dd>{{ fcfa(r.cashed) }}</dd>
                    </div>
                    <div class="mt-2 border-t pt-2 text-muted-foreground">
                        Coût réel par nature
                    </div>
                    <div class="flex justify-between">
                        <dt>Matériaux</dt>
                        <dd>{{ fcfa(r.cost.materiaux) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Main-d’œuvre</dt>
                        <dd>{{ fcfa(r.cost.main_oeuvre) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Matériel</dt>
                        <dd>{{ fcfa(r.cost.materiel) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-1.5 font-medium"
                    >
                        <dt>Coût réel total</dt>
                        <dd>{{ fcfa(r.totalCost) }}</dd>
                    </div>
                    <div
                        class="flex justify-between rounded-lg p-2 font-display text-base font-semibold"
                        :class="
                            r.margin >= 0
                                ? 'bg-success-soft text-success'
                                : 'bg-danger-soft text-danger'
                        "
                    >
                        <dt>Marge réelle</dt>
                        <dd>{{ fcfa(r.margin) }} · {{ percent(r.rate, 1) }}</dd>
                    </div>
                </dl>
            </article>
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Facturé et coût réel
            </h2>
            <BarChart :labels="labels" :datasets="sets" />
        </section>
    </div>
</template>
