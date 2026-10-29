<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { computeSituation } from '@/lib/billing';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa, percent } from '@/lib/format';
import { statuses } from '@/lib/status';
import { ca11Example, situations } from '@/mocks/v3';
import type { Situation } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Situations de travaux', href: '/situations' }],
    },
});

type Row = Situation & { amount: number; netTtc: number };
const rows: Row[] = situations.map((s) => ({
    ...s,
    amount: computeSituation(s).amount,
    netTtc: computeSituation(s).netTtc,
}));
const example = computeSituation(ca11Example);

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Situation',
        cell: ({ row }) =>
            linkCell(
                `/situations/${row.original.id}`,
                `n°${row.original.number}`,
                row.original.contractNumber,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'progressCumul',
        header: 'Avancement cumulé',
        cell: ({ row }) => percent(row.original.progressCumul),
    },
    {
        accessorKey: 'amount',
        header: 'Travaux de la période',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        accessorKey: 'netTtc',
        header: 'Net à payer TTC',
        cell: ({ row }) => amountCell(fcfa(row.original.netTtc)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('situation', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.situation).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Situations de travaux" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Situations de travaux"
            description="Factures intermédiaires calculées sur l’avancement réel. Une situation validée est figée ; une correction passe par la suivante (RG11.3)."
        />
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher une situation…"
        />

        <aside class="rounded-xl border bg-card p-4 text-sm shadow-xs">
            <h2 class="mb-1 font-display font-semibold">
                Vérification : exemple du cahier des charges (CA11)
            </h2>
            <p class="text-muted-foreground">
                Marché de 100 000 000 FCFA, avancement cumulé de 30 % à 40 %,
                retenue 5 %, remboursement d’acompte 10 %.
            </p>
            <p class="mt-1 tabular-nums">
                Situation : <strong>{{ fcfa(example.amount) }}</strong> −
                {{ fcfa(example.retention) }} −
                {{ fcfa(example.advanceRefund) }} = net à payer hors taxes
                <strong>{{ fcfa(example.netHt) }}</strong
                >.
            </p>
        </aside>
    </div>
</template>
