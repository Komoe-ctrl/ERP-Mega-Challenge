<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { h } from 'vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr } from '@/lib/format';
import { globalProgress, taskHealth } from '@/lib/planning';
import { statuses } from '@/lib/status';
import { chantiers, tasksOf } from '@/mocks/chantiers';
import type { Chantier } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const { can } = usePermissions();

type Row = Chantier & { progress: number; late: number };

const rows: Row[] = chantiers.map((c) => {
    const t = tasksOf(c.id);

    return {
        ...c,
        progress: globalProgress(t),
        late: t.filter((x) => ['late', 'risk', 'stale'].includes(taskHealth(x)))
            .length,
    };
});

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Chantier',
        cell: ({ row }) =>
            linkCell(
                `/chantiers/${row.original.id}`,
                row.original.name,
                row.original.code,
            ),
    },
    { accessorKey: 'projectName', header: 'Projet' },
    { accessorKey: 'foreman', header: 'Chef de chantier' },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('site', row.original.status),
    },
    {
        accessorKey: 'progress',
        header: 'Avancement',
        cell: ({ row }) =>
            h('div', { class: 'w-32' }, [
                h(ProgressBar, { value: row.original.progress, tone: 'info' }),
            ]),
    },
    {
        accessorKey: 'late',
        header: 'Alertes',
        cell: ({ row }) =>
            row.original.late > 0
                ? h(StatusBadge, {
                      label: `${row.original.late} tâche(s)`,
                      tone: 'warning',
                  })
                : h('span', { class: 'text-muted-foreground' }, '—'),
    },
    {
        accessorKey: 'endDate',
        header: 'Fin prévue',
        cell: ({ row }) => dateFr(row.original.endDate),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.site).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Chantiers" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Chantiers"
            description="Un utilisateur de terrain ne voit que les chantiers auxquels il est affecté."
        >
            <Button v-if="can('chantiers.create')" as-child>
                <Link href="/chantiers/creer"><Plus /> Nouveau chantier</Link>
            </Button>
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un chantier…"
        />
    </div>
</template>
