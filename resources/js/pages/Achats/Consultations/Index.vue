<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { consultations } from '@/mocks/v2';
import type { Consultation } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Consultations', href: '/achats/consultations' },
        ],
    },
});

const columns: DataTableColumn<Consultation>[] = [
    {
        accessorKey: 'number',
        header: 'Consultation',
        cell: ({ row }) =>
            linkCell(
                `/achats/consultations/${row.original.id}`,
                row.original.number,
                row.original.items,
            ),
    },
    { accessorKey: 'requestNumber', header: 'Demande' },
    { accessorKey: 'chantierName', header: 'Chantier' },
    {
        id: 'offers',
        header: 'Offres reçues',
        accessorFn: (c) => c.quotes.length,
        cell: ({ row }) =>
            `${row.original.quotes.length} / ${row.original.minSuppliers} minimum`,
    },
    {
        id: 'best',
        header: 'Meilleure offre',
        accessorFn: (c) => Math.min(...c.quotes.map((q) => q.unitTotal)),
        cell: ({ row }) =>
            amountCell(
                fcfa(Math.min(...row.original.quotes.map((q) => q.unitTotal))),
            ),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('consultation', row.original.status),
    },
];
</script>

<template>
    <Head title="Consultations" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Consultations de fournisseurs"
            description="Au-delà du seuil paramétré, au moins trois fournisseurs sont consultés avant commande (RG8.11)."
        />
        <SubNav :links="achatsNav" />
        <DataTable
            :columns="columns"
            :data="consultations"
            :searchable="false"
        />
    </div>
</template>
