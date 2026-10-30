<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { number } from '@/lib/format';
import { financeNav } from '@/lib/navs';
import { treasury } from '@/mocks/v4';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Comptabilité générale',
                href: '/ecrans/comptabilite-generale',
            },
            { title: 'Trésorerie', href: '/finance/tresorerie' },
        ],
    },
});

// Solde cumulé de la période (M FCFA), à partir d'un solde d'ouverture de 90 M.
const rows = computed(() => {
    let balance = 90;

    return treasury.map((t) => {
        balance += t.inflow - t.outflow;

        return { ...t, net: t.inflow - t.outflow, balance };
    });
});

const labels = computed(() => rows.value.map((r) => r.month));
const sets = computed(() => [
    {
        label: 'Solde cumulé (M FCFA)',
        data: rows.value.map((r) => Math.round(r.balance * 10) / 10),
    },
]);
</script>

<template>
    <Head title="Trésorerie" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Trésorerie"
            description="Encaissements, décaissements et solde cumulé, en millions de FCFA."
        />
        <SubNav :links="financeNav" />

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <BarChart :labels="labels" :datasets="sets" />
        </section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="p-3 font-medium">Mois</th>
                            <th class="p-3 text-right font-medium">
                                Encaissements
                            </th>
                            <th class="p-3 text-right font-medium">
                                Décaissements
                            </th>
                            <th class="p-3 text-right font-medium">Flux net</th>
                            <th class="p-3 text-right font-medium">
                                Solde cumulé
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.month" class="border-t">
                            <td class="p-3 font-medium">{{ r.month }}</td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(r.inflow, 1) }} M
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ number(r.outflow, 1) }} M
                            </td>
                            <td
                                class="p-3 text-right tabular-nums"
                                :class="
                                    r.net < 0 ? 'text-danger' : 'text-success'
                                "
                            >
                                {{ r.net > 0 ? '+' : ''
                                }}{{ number(r.net, 1) }} M
                            </td>
                            <td class="p-3 text-right font-medium tabular-nums">
                                {{ number(r.balance, 1) }} M
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
