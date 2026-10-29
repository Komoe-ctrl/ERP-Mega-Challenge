<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { h } from 'vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, daysBetween, fcfa, number, TODAY } from '@/lib/format';
import { statuses } from '@/lib/status';
import { equipments } from '@/mocks/v4';
import type { Equipment } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Engins et équipements', href: '/engins' }],
    },
});

// RG14.3 : une alerte part avant l'échéance de maintenance (ici 14 jours).
const dueSoon = (e: Equipment) => daysBetween(TODAY, e.nextMaintenance) <= 14;

const columns: DataTableColumn<Equipment>[] = [
    {
        accessorKey: 'name',
        header: 'Engin',
        cell: ({ row }) =>
            linkCell(
                `/engins/${row.original.id}`,
                row.original.name,
                row.original.code,
            ),
    },
    { accessorKey: 'type', header: 'Type', filterFn: 'equalsString' },
    { accessorKey: 'chantier', header: 'Chantier actuel' },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('equipment', row.original.status),
    },
    {
        accessorKey: 'hours',
        header: 'Heures',
        cell: ({ row }) => amountCell(number(row.original.hours)),
    },
    {
        accessorKey: 'nextMaintenance',
        header: 'Prochaine maintenance',
        cell: ({ row }) =>
            h('div', { class: 'flex items-center gap-2' }, [
                dateFr(row.original.nextMaintenance),
                dueSoon(row.original)
                    ? h(StatusBadge, {
                          label: 'Échéance proche',
                          tone: 'warning',
                      })
                    : null,
            ]),
    },
    {
        accessorKey: 'repairCost',
        header: 'Réparations',
        cell: ({ row }) => amountCell(fcfa(row.original.repairCost)),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.equipment).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
    {
        columnId: 'type',
        label: 'Type',
        options: [...new Set(equipments.map((e) => e.type))].map((t) => ({
            value: t,
            label: t,
        })),
    },
];
</script>

<template>
    <Head title="Engins et équipements" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Engins et équipements"
            description="Un engin est affecté à un seul chantier à la fois ; pendant une réparation, il est indisponible (RG14.1, RG14.4)."
        />
        <DataTable
            :columns="columns"
            :data="equipments"
            :filters="filters"
            search-placeholder="Rechercher un engin…"
        />
    </div>
</template>
