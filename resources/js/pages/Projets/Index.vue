<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import { projects } from '@/mocks/chantiers';
import type { Project } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const { can } = usePermissions();

const columns: DataTableColumn<Project>[] = [
    {
        accessorKey: 'name',
        header: 'Projet',
        cell: ({ row }) =>
            linkCell(
                `/projets/${row.original.id}`,
                row.original.name,
                row.original.number,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    { accessorKey: 'manager', header: 'Responsable' },
    { accessorKey: 'agency', header: 'Agence' },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('project', row.original.status),
    },
    {
        accessorKey: 'budget',
        header: 'Budget',
        cell: ({ row }) => amountCell(fcfa(row.original.budget)),
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
        options: Object.entries(statuses.project).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
    {
        columnId: 'manager',
        label: 'Responsable',
        options: [...new Set(projects.map((p) => p.manager))].map((m) => ({
            value: m,
            label: m,
        })),
    },
    {
        columnId: 'agency',
        label: 'Agence',
        options: [...new Set(projects.map((p) => p.agency))].map((a) => ({
            value: a,
            label: a,
        })),
    },
];
</script>

<template>
    <Head title="Projets" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Projets"
            description="Du premier contact au dossier clôturé."
        >
            <Button v-if="can('projets.create')" as-child>
                <Link href="/projets/creer"><Plus /> Nouveau projet</Link>
            </Button>
        </PageHeader>

        <DataTable
            :columns="columns"
            :data="projects"
            :filters="filters"
            search-placeholder="Rechercher un projet, un client…"
        />
    </div>
</template>
