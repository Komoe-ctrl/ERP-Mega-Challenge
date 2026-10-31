<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ContractController from '@/actions/App/Http/Controllers/Marches/ContractController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { amountCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import type { Paginated } from '@/types';
import type { FieldDef, FieldValues } from '@/types/forms';

type ContractRow = {
    id: number;
    number: string;
    clientName: string;
    projectName: string;
    signedAt: string;
    initialAmount: number;
    updated: number;
    days: number;
};

const props = defineProps<{
    contracts: Paginated<ContractRow>;
    filters: { q: string };
    signableQuotes: { id: number; label: string }[];
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Marchés', href: '/marches' }] },
});

const createOpen = ref(false);
const search = ref(props.filters.q);

function apply() {
    router.get(
        ContractController.index.url(),
        search.value ? { q: search.value } : {},
        { preserveState: true, replace: true },
    );
}

// RG6.1 : un marché naît d'un devis validé ; son montant initial reprend celui du devis.
const fields: FieldDef[] = [
    {
        name: 'quote_id',
        label: 'Devis validé',
        type: 'select',
        required: true,
        wide: true,
        options: props.signableQuotes.map((q) => ({
            value: String(q.id),
            label: q.label,
        })),
        hint: props.signableQuotes.length
            ? undefined
            : 'Aucun devis validé sans marché.',
    },
    {
        name: 'signed_at',
        label: 'Date de signature',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) > TODAY
                ? 'La signature ne peut pas être dans le futur.'
                : null,
    },
    {
        name: 'deadline_days',
        label: 'Délai contractuel',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'jours',
    },
    {
        name: 'advance_rate',
        label: 'Taux d’acompte',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
    {
        name: 'retention_rate',
        label: 'Retenue de garantie',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
    {
        name: 'payment_days',
        label: 'Délai de paiement',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        unit: 'jours',
    },
];

function create(v: FieldValues) {
    router.post(
        ContractController.store.url(),
        {
            quote_id: Number(v.quote_id),
            signed_at: String(v.signed_at),
            deadline_days: Number(v.deadline_days),
            advance_rate: Number(v.advance_rate),
            retention_rate: Number(v.retention_rate),
            payment_days: Number(v.payment_days),
        },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Saisie invalide.'),
        },
    );
}

const columns: DataTableColumn<ContractRow>[] = [
    {
        accessorKey: 'number',
        header: 'Marché',
        cell: ({ row }) =>
            linkCell(
                ContractController.show.url(row.original.id),
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
            description="Un marché naît d’un devis validé ; ses avenants validés modifient le montant et le délai."
        >
            <Button v-if="can.create" @click="createOpen = true"
                ><Plus /> Nouveau marché</Button
            >
        </PageHeader>

        <form class="max-w-xs" @submit.prevent="apply">
            <Input
                v-model="search"
                type="search"
                placeholder="Rechercher un marché…"
                aria-label="Rechercher un marché"
            />
        </form>

        <DataTable
            :columns="columns"
            :data="contracts.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun marché."
        />
        <PaginationBar :page="contracts" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouveau marché"
            :initial="{ advance_rate: 10, retention_rate: 5, payment_days: 30 }"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
