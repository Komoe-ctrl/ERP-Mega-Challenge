<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import { quoteTotals } from '@/lib/billing';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa, percent } from '@/lib/format';
import { statuses } from '@/lib/status';
import { quotes } from '@/mocks/v3';
import type { QuoteDoc } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Devis', href: '/devis' }] },
});

type Row = QuoteDoc & { ht: number; marginRate: number };
const rows: Row[] = quotes.map((q) => ({
    ...q,
    ht: quoteTotals(q).ht,
    marginRate: quoteTotals(q).marginRate,
}));

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Devis',
        cell: ({ row }) =>
            linkCell(
                `/devis/${row.original.id}`,
                `${row.original.number} (v${row.original.version})`,
                row.original.projectName,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'ht',
        header: 'Total HT',
        cell: ({ row }) => amountCell(fcfa(row.original.ht)),
    },
    {
        accessorKey: 'marginRate',
        header: 'Marge prévue',
        cell: ({ row }) => amountCell(percent(row.original.marginRate, 1)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('quote', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.quote).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Devis" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Devis"
            description="Brouillon → soumis → validé ou refusé. Un devis refusé peut être dupliqué en nouvelle version."
        />
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un devis…"
        />
    </div>
</template>
