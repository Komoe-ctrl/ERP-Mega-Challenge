<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { statuses } from '@/lib/status';
import { orders } from '@/mocks/v2';
import type { PurchaseOrder } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Bons de commande', href: '/achats/commandes' },
        ],
    },
});

const columns: DataTableColumn<PurchaseOrder>[] = [
    {
        accessorKey: 'number',
        header: 'Bon de commande',
        cell: ({ row }) =>
            linkCell(
                `/achats/commandes/${row.original.id}`,
                row.original.number,
                row.original.requestNumbers.join(', '),
            ),
    },
    { accessorKey: 'supplierName', header: 'Fournisseur' },
    { accessorKey: 'chantierName', header: 'Chantier' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'amount',
        header: 'Montant',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('order', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.order).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Bons de commande" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Bons de commande"
            description="Un bon peut regrouper plusieurs demandes d’un même chantier ; une demande peut être servie par plusieurs commandes (RG8.8)."
        />
        <SubNav :links="achatsNav" />
        <DataTable
            :columns="columns"
            :data="orders"
            :filters="filters"
            search-placeholder="Rechercher un bon de commande…"
        />
    </div>
</template>
