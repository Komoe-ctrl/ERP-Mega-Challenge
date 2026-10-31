<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { amountCell, linkCell } from '@/lib/cells';
import { clientTypeLabels } from '@/lib/clients';
import type { DataTableColumn } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import type { Paginated } from '@/types';
import type { ClientRecord } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{
    clients: Paginated<ClientRecord>;
    filters: { q: string; type: string };
    types: string[];
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

const createOpen = ref(false);
const search = ref(props.filters.q);

function apply(changes: Partial<{ q: string; type: string }>) {
    const next = { ...props.filters, ...changes };

    router.get(
        ClientController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

// RG2.1 : un doublon sur le nom et le contact est signalé à la création ; on peut le confirmer.
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
    { name: 'contact', label: 'Contact principal', required: true },
    {
        name: 'phone',
        label: 'Téléphone',
        type: 'tel',
        required: true,
        pattern: /^\+?[\d\s]{8,18}$/,
        patternMessage: 'Numéro invalide (ex. +225 07 00 00 00 00).',
    },
    { name: 'email', label: 'E-mail', type: 'email' },
    { name: 'address', label: 'Adresse', wide: true },
];

function create(v: FieldValues, confirmDuplicate = false) {
    router.post(
        ClientController.store.url(),
        {
            ...(v as Record<string, string>),
            confirm_duplicate: confirmDuplicate,
        },
        {
            onError: (errors) => {
                const message = Object.values(errors)[0] ?? 'Saisie invalide.';

                if (errors.name?.startsWith('Doublon')) {
                    toast.warning(errors.name, {
                        duration: 15000,
                        action: {
                            label: 'Créer quand même',
                            onClick: () => create(v, true),
                        },
                    });

                    return;
                }

                toast.error(message);
            },
        },
    );
}

const columns: DataTableColumn<ClientRecord>[] = [
    {
        accessorKey: 'name',
        header: 'Client',
        cell: ({ row }) =>
            linkCell(
                ClientController.show.url(row.original.id),
                row.original.name,
                row.original.number,
            ),
    },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => clientTypeLabels[row.original.type],
    },
    {
        accessorKey: 'contact',
        header: 'Contact',
        cell: ({ row }) =>
            [row.original.contact, row.original.phone]
                .filter(Boolean)
                .join(' · ') || '—',
    },
    { accessorKey: 'city', header: 'Ville' },
    { accessorKey: 'projectsInProgress', header: 'Projets en cours' },
    {
        id: 'due',
        header: 'Solde dû',
        cell: ({ row }) =>
            amountCell(fcfa(row.original.invoiced - row.original.paid)),
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
            <Button v-if="can.create" @click="createOpen = true"
                ><Plus /> Nouveau client</Button
            >
        </PageHeader>

        <div class="flex flex-wrap gap-3">
            <form
                class="flex-1 sm:max-w-xs"
                @submit.prevent="apply({ q: search })"
            >
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher un client…"
                    aria-label="Rechercher un client"
                />
            </form>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par type"
                :value="filters.type"
                @change="
                    apply({ type: ($event.target as HTMLSelectElement).value })
                "
            >
                <option value="">Type : tous</option>
                <option v-for="t in types" :key="t" :value="t">
                    {{ clientTypeLabels[t] }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="clients.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun client."
        />
        <PaginationBar :page="clients" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouveau client"
            :fields="fields"
            @submit="(v) => create(v)"
        />
    </div>
</template>
