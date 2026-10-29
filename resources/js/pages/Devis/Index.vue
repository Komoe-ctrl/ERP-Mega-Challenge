<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { quoteTotals } from '@/lib/billing';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa, percent } from '@/lib/format';
import { statuses } from '@/lib/status';
import { projects } from '@/mocks/chantiers';
import { clients, quotes } from '@/mocks/v3';
import type { QuoteDoc } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Devis', href: '/devis' }] },
});

const { can } = usePermissions();
const createOpen = ref(false);

type Row = QuoteDoc & { ht: number; marginRate: number };
const rows: Row[] = quotes.map((q) => ({
    ...q,
    ht: quoteTotals(q).ht,
    marginRate: quoteTotals(q).marginRate,
}));

const fields: FieldDef[] = [
    {
        name: 'client',
        label: 'Client',
        type: 'select',
        required: true,
        options: clients.map((c) => ({ value: c.name, label: c.name })),
        wide: true,
    },
    {
        name: 'project',
        label: 'Projet',
        type: 'select',
        required: true,
        options: projects
            .filter((p) =>
                ['etude', 'devis', 'appel_offres'].includes(p.status),
            )
            .map((p) => ({ value: p.name, label: p.name })),
        wide: true,
        hint: 'Seuls les projets au stade étude, devis ou appel d’offres sont proposés.',
    },
    {
        name: 'discount',
        label: 'Remise',
        type: 'number',
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
    {
        name: 'vat',
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
    toast.success(
        `Devis créé en brouillon pour ${String(v.client)} (maquette). Ajoutez ses ouvrages depuis l’éditeur.`,
    );
    router.visit('/devis/3');
}

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Devis',
        cell: ({ row }) =>
            linkCell(
                `/devis/${row.original.id}`,
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
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('quote', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.quote).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
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
            <Button v-if="can('devis.create')" @click="createOpen = true"
                ><Plus /> Nouveau devis</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un devis…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouveau devis"
            :initial="{ vat: 18, discount: 0 }"
            :fields="fields"
            submit-label="Créer le brouillon"
            @submit="create"
        />
    </div>
</template>
