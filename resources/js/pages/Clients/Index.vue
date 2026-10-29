<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { clients, clientTypeLabels } from '@/mocks/v3';
import type { ClientRecord } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

const { can } = usePermissions();

const columns: DataTableColumn<ClientRecord>[] = [
    {
        accessorKey: 'name',
        header: 'Client',
        cell: ({ row }) =>
            linkCell(
                `/clients/${row.original.id}`,
                row.original.name,
                row.original.number,
            ),
    },
    {
        accessorKey: 'type',
        header: 'Type',
        filterFn: 'equalsString',
        cell: ({ row }) => clientTypeLabels[row.original.type],
    },
    {
        accessorKey: 'contact',
        header: 'Contact',
        cell: ({ row }) => `${row.original.contact} · ${row.original.phone}`,
    },
    { accessorKey: 'city', header: 'Ville' },
    { accessorKey: 'projectsInProgress', header: 'Projets en cours' },
    {
        id: 'due',
        header: 'Solde dû',
        accessorFn: (c) => c.invoiced - c.paid,
        cell: ({ row }) =>
            amountCell(fcfa(row.original.invoiced - row.original.paid)),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'type',
        label: 'Type',
        options: Object.entries(clientTypeLabels).map(([value, label]) => ({
            value,
            label,
        })),
    },
];
</script>

<template>
    <Head title="Clients" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Clients"
            description="Un doublon sur le nom et le contact est signalé à la création (RG2.1)."
        >
            <Button
                v-if="can('clients.create')"
                @click="toast.success('Création d’un client (maquette).')"
                ><Plus /> Nouveau client</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="clients"
            :filters="filters"
            search-placeholder="Rechercher un client…"
        />
    </div>
</template>
