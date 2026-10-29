<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight, Landmark, Wallet } from '@lucide/vue';
import BarChart from '@/components/charts/BarChart.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { fcfa, fcfaShort } from '@/lib/format';
import { financeNav } from '@/lib/navs';
import { bankBalance, cashBalance, cashEntries, treasury } from '@/mocks/v4';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Finance', href: '/finance' }] },
});

const month = cashEntries.filter((e) => e.date.startsWith('2026-10'));
const inflow = month
    .filter((e) => e.kind === 'recette')
    .reduce((s, e) => s + e.amount, 0);
const outflow = month
    .filter((e) => e.kind === 'depense')
    .reduce((s, e) => s + e.amount, 0);

const labels = treasury.map((t) => t.month);
const sets = [
    { label: 'Encaissements (M FCFA)', data: treasury.map((t) => t.inflow) },
    { label: 'Décaissements (M FCFA)', data: treasury.map((t) => t.outflow) },
];
</script>

<template>
    <Head title="Finance" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Finance et comptabilité"
            description="Caisse, banque, recettes, dépenses, rapprochement et rentabilité. Exercice 2026 ouvert."
        />
        <SubNav :links="financeNav" />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                label="Solde banque"
                :value="fcfaShort(bankBalance)"
                :icon="Landmark"
                tone="info"
            />
            <KpiCard
                label="Solde caisse"
                :value="fcfa(cashBalance)"
                :icon="Wallet"
                tone="success"
                hint="Ne peut pas devenir négatif"
            />
            <KpiCard
                label="Recettes d’octobre"
                :value="fcfaShort(inflow)"
                :icon="ArrowUpRight"
                tone="success"
            />
            <KpiCard
                label="Dépenses d’octobre"
                :value="fcfaShort(outflow)"
                :icon="ArrowDownRight"
                tone="warning"
            />
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Encaissements et décaissements par mois
            </h2>
            <BarChart :labels="labels" :datasets="sets" />
        </section>
    </div>
</template>
