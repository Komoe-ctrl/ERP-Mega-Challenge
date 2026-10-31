<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import QuoteController from '@/actions/App/Http/Controllers/Devis/QuoteController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa, percent } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { FieldDef, FieldValues } from '@/types/forms';

type QuoteRow = {
    id: number;
    number: string;
    version: number;
    clientName: string;
    projectName: string;
    date: string;
    status: string;
    ht: number;
    marginRate: number;
};

const props = defineProps<{
    quotes: Paginated<QuoteRow>;
    filters: { q: string; status: string };
    clients: { id: number; name: string }[];
    projects: { id: number; name: string; client_id: number }[];
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Devis', href: '/devis' }] },
});

const createOpen = ref(false);
const search = ref(props.filters.q);

function apply(changes: Partial<{ q: string; status: string }>) {
    const next = { ...props.filters, ...changes };

    router.get(
        QuoteController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

const clientName = (id: number) =>
    props.clients.find((c) => c.id === id)?.name ?? '';

// Le client est celui du projet choisi.
const fields: FieldDef[] = [
    {
        name: 'project_id',
        label: 'Projet',
        type: 'select',
        required: true,
        wide: true,
        options: props.projects.map((p) => ({
            value: String(p.id),
            label: `${p.name} — ${clientName(p.client_id)}`,
        })),
        hint: 'Seuls les projets au stade étude, devis ou appel d’offres sont proposés.',
    },
    {
        name: 'discount_rate',
        label: 'Remise',
        type: 'number',
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
    {
        name: 'vat_rate',
        label: 'TVA',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
];

function create(v: FieldValues) {
    const project = props.projects.find((p) => p.id === Number(v.project_id));

    router.post(
        QuoteController.store.url(),
        {
            client_id: project?.client_id,
            project_id: Number(v.project_id),
            discount_rate: Number(v.discount_rate ?? 0),
            vat_rate: Number(v.vat_rate),
        },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Saisie invalide.'),
        },
    );
}

const columns: DataTableColumn<QuoteRow>[] = [
    {
        accessorKey: 'number',
        header: 'Devis',
        cell: ({ row }) =>
            linkCell(
                QuoteController.show.url(row.original.id),
                `${row.original.number} (v${row.original.version})`,
                row.original.projectName,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'ht',
        header: 'Total HT',
        cell: ({ row }) => amountCell(fcfa(row.original.ht)),
    },
    {
        accessorKey: 'marginRate',
        header: 'Marge prévue',
        cell: ({ row }) => amountCell(percent(row.original.marginRate, 1)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('quote', row.original.status),
    },
];
</script>

<template>
    <Head title="Devis" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Devis"
            description="Brouillon → soumis → validé ou refusé. Un devis refusé peut être dupliqué en nouvelle version."
        >
            <Button v-if="can.create" @click="createOpen = true"
                ><Plus /> Nouveau devis</Button
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
                    placeholder="Rechercher un devis…"
                    aria-label="Rechercher un devis"
                />
            </form>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par statut"
                :value="filters.status"
                @change="
                    apply({
                        status: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Statut : tous</option>
                <option
                    v-for="(meta, value) in statuses.quote"
                    :key="value"
                    :value="value"
                >
                    {{ meta.label }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="quotes.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun devis."
        />
        <PaginationBar :page="quotes" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouveau devis"
            :initial="{ vat_rate: 18, discount_rate: 0 }"
            :fields="fields"
            submit-label="Créer le brouillon"
            @submit="create"
        />
    </div>
</template>
