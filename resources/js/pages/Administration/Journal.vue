<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { h } from 'vue';
import DataTable from '@/components/DataTable.vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { badgeCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateTimeFr } from '@/lib/format';
import { statuses } from '@/lib/status';
import { activityLog } from '@/mocks/transverse';
import type { ActivityEntry } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Journal des activités', href: '/administration/journal' },
        ],
    },
});

const columns: DataTableColumn<ActivityEntry>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateTimeFr(row.original.date.replace(' ', 'T')),
    },
    { accessorKey: 'actor', header: 'Auteur', filterFn: 'equalsString' },
    {
        accessorKey: 'event',
        header: 'Événement',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('activity', row.original.event),
    },
    { accessorKey: 'subject', header: 'Objet' },
    {
        id: 'changes',
        header: 'Valeurs avant → après',
        cell: ({ row }) =>
            h(
                'ul',
                { class: 'space-y-0.5 text-xs' },
                row.original.changes.map((c) =>
                    h('li', [
                        h(
                            'span',
                            { class: 'text-muted-foreground' },
                            `${c.field} : `,
                        ),
                        h(
                            'span',
                            { class: 'line-through opacity-70' },
                            c.before,
                        ),
                        ' → ',
                        h('span', { class: 'font-medium' }, c.after),
                    ]),
                ),
            ),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'event',
        label: 'Événement',
        options: Object.entries(statuses.activity).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
    {
        columnId: 'actor',
        label: 'Auteur',
        options: [...new Set(activityLog.map((a) => a.actor))].map((a) => ({
            value: a,
            label: a,
        })),
    },
];
</script>

<template>
    <Head title="Journal des activités" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Journal des activités"
            description="Toute création, modification, validation ou suppression : auteur, date, valeurs avant et après. Non modifiable."
        />
        <AdminNav />
        <DataTable
            :columns="columns"
            :data="activityLog"
            :filters="filters"
            search-placeholder="Rechercher dans le journal…"
        />
    </div>
</template>
