<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { clients, clientTypeLabels } from '@/mocks/v3';
import type { ClientRecord } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

const { can } = usePermissions();
const rows = ref<ClientRecord[]>(clients.map((c) => ({ ...c })));
const createOpen = ref(false);

const norm = (s: unknown) =>
    String(s ?? '')
        .trim()
        .toLowerCase();

// RG2.1 : un doublon sur le nom et le contact est signalé à la création.
const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Nom ou raison sociale',
        required: true,
        minLength: 3,
        wide: true,
    },
    {
        name: 'type',
        label: 'Type de client',
        type: 'select',
        required: true,
        options: Object.entries(clientTypeLabels).map(([value, label]) => ({
            value,
            label,
        })),
    },
    { name: 'city', label: 'Ville', required: true },
    {
        name: 'contact',
        label: 'Contact principal',
        required: true,
        validate: (value, values) =>
            rows.value.some(
                (c) =>
                    norm(c.name) === norm(values.name) &&
                    norm(c.contact) === norm(value),
            )
                ? 'Doublon possible : un client de même nom et même contact existe déjà (RG2.1).'
                : null,
    },
    {
        name: 'phone',
        label: 'Téléphone',
        type: 'tel',
        required: true,
        pattern: /^\+?[\d\s]{8,18}$/,
        patternMessage: 'Numéro invalide (ex. +225 07 00 00 00 00).',
    },
    { name: 'email', label: 'E-mail', type: 'email' },
];

function create(v: FieldValues) {
    const number = `CLI-${String(rows.value.length + 1).padStart(4, '0')}`;

    rows.value.push({
        id: Date.now(),
        number,
        name: String(v.name),
        type: v.type as ClientRecord['type'],
        contact: String(v.contact),
        phone: String(v.phone),
        city: String(v.city),
        projectsInProgress: 0,
        invoiced: 0,
        paid: 0,
    });
    toast.success(`Client créé sous le numéro ${number} (maquette).`);
}

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
            description="Un client est identifié par un numéro unique ; un doublon est signalé à la création (RG2.1)."
        >
            <Button v-if="can('clients.create')" @click="createOpen = true"
                ><Plus /> Nouveau client</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un client…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouveau client"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
