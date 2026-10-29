<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { contractTotals } from '@/lib/billing';
import { amountCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { contracts } from '@/mocks/v3';
import type { Contract } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Marchés', href: '/marches' }] },
});

type Row = Contract & { updated: number; days: number };
const rows: Row[] = contracts.map((c) => ({
    ...c,
    updated: contractTotals(c).updatedAmount,
    days: contractTotals(c).contractualDays,
}));

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Marché',
        cell: ({ row }) =>
            linkCell(
                `/marches/${row.original.id}`,
                row.original.number,
                row.original.projectName,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    {
        accessorKey: 'signedAt',
        header: 'Signé le',
        cell: ({ row }) => dateFr(row.original.signedAt),
    },
    {
        accessorKey: 'initialAmount',
        header: 'Montant initial',
        cell: ({ row }) => amountCell(fcfa(row.original.initialAmount)),
    },
    {
        accessorKey: 'updated',
        header: 'Montant actualisé',
        cell: ({ row }) => amountCell(fcfa(row.original.updated)),
    },
    {
        accessorKey: 'days',
        header: 'Délai',
        cell: ({ row }) => `${row.original.days} jours`,
    },
];
</script>

<template>
    <Head title="Marchés et contrats" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Marchés et contrats"
            description="Un marché naît d’un devis validé ou d’un appel d’offres gagné ; ses avenants modifient le montant et le délai."
        />
        <DataTable :columns="columns" :data="rows" :searchable="false" />
    </div>
</template>
