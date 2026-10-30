<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, number, TODAY } from '@/lib/format';
import type { Tone } from '@/lib/status';
import { chantiers } from '@/mocks/chantiers';
import { stockItems, stockMovements } from '@/mocks/v2';
import type { StockMovement } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stocks', href: '/stocks' },
            { title: 'Mouvements', href: '/stocks/mouvements' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<StockMovement[]>(stockMovements.map((m) => ({ ...m })));
const createOpen = ref(false);

const types: Record<StockMovement['type'], { label: string; tone: Tone }> = {
    entree: { label: 'Entrée', tone: 'success' },
    sortie: { label: 'Sortie', tone: 'info' },
    transfert: { label: 'Transfert', tone: 'brand' },
    inventaire: { label: 'Inventaire', tone: 'neutral' },
    retour: { label: 'Retour', tone: 'warning' },
    perte: { label: 'Perte', tone: 'danger' },
    ajustement: { label: 'Ajustement', tone: 'warning' },
};

const warehouses = [...new Set(stockItems.map((s) => s.warehouse))];
const needsReason = ['perte', 'ajustement'];

const fields = computed<FieldDef[]>(() => [
    {
        name: 'type',
        label: 'Opération',
        type: 'select',
        required: true,
        options: Object.entries(types)
            .filter(([k]) => k !== 'inventaire')
            .map(([value, t]) => ({ value, label: t.label })),
    },
    {
        name: 'item',
        label: 'Article',
        type: 'select',
        required: true,
        options: [...new Set(stockItems.map((s) => s.label))].map((l) => ({
            value: l,
            label: l,
        })),
    },
    {
        name: 'quantity',
        label: 'Quantité',
        type: 'number',
        required: true,
        min: 0.001,
        step: 0.001,
        hint: 'Quantité décimale acceptée (m³, kg…).',
    },
    {
        name: 'warehouse',
        label: 'Magasin',
        type: 'select',
        required: true,
        options: warehouses.map((w) => ({ value: w, label: w })),
    },
    {
        name: 'destination',
        label: 'Destination (transfert)',
        type: 'select',
        options: warehouses.map((w) => ({ value: w, label: w })),
        validate: (v, values) =>
            values.type === 'transfert' && (!v || v === values.warehouse)
                ? 'Choisissez un magasin de destination différent.'
                : null,
    },
    {
        name: 'chantier',
        label: 'Chantier (sortie)',
        type: 'select',
        options: chantiers
            .filter((c) => c.status === 'en_cours')
            .map((c) => ({ value: c.name, label: c.name })),
        validate: (v, values) =>
            values.type === 'sortie' && !v
                ? 'Une sortie est imputée à un chantier (coût réel, RG9.2).'
                : null,
    },
    {
        name: 'reason',
        label: 'Motif',
        type: 'textarea',
        validate: (v, values) =>
            needsReason.includes(String(values.type)) && !String(v ?? '').trim()
                ? 'Un motif est obligatoire pour une perte ou un ajustement (RG9.3).'
                : null,
    },
]);

function create(v: FieldValues) {
    const type = String(v.type) as StockMovement['type'];
    const item = stockItems.find((s) => s.label === v.item);

    rows.value.unshift({
        id: Date.now(),
        date: TODAY,
        type,
        label: String(v.item),
        quantity: Number(v.quantity),
        unit: item?.unit ?? '',
        from: String(v.warehouse),
        to:
            type === 'transfert'
                ? String(v.destination)
                : type === 'sortie'
                  ? `Chantier ${String(v.chantier)}`
                  : '—',
        chantier: v.chantier ? String(v.chantier) : undefined,
        author: 'Vous',
        reason: v.reason ? String(v.reason) : undefined,
    });
    toast.success(
        needsReason.includes(type)
            ? 'Mouvement enregistré, en attente de validation ; visible dans le journal des activités (maquette).'
            : 'Mouvement enregistré (maquette).',
    );
}

const columns: DataTableColumn<StockMovement>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'type',
        header: 'Opération',
        filterFn: 'equalsString',
        cell: ({ row }) => h(StatusBadge, types[row.original.type]),
    },
    { accessorKey: 'label', header: 'Article' },
    {
        accessorKey: 'quantity',
        header: 'Quantité',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                `${number(row.original.quantity, row.original.quantity % 1 ? 3 : 0)} ${row.original.unit}`,
            ),
    },
    {
        id: 'route',
        header: 'De → vers',
        accessorFn: (m) => `${m.from} ${m.to}`,
        cell: ({ row }) => `${row.original.from} → ${row.original.to}`,
    },
    { accessorKey: 'author', header: 'Par' },
    {
        accessorKey: 'reason',
        header: 'Motif',
        cell: ({ row }) => row.original.reason ?? '—',
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'type',
        label: 'Opération',
        options: Object.entries(types).map(([value, t]) => ({
            value,
            label: t.label,
        })),
    },
];
</script>

<template>
    <Head title="Mouvements de stock" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Mouvements de stock"
            description="Entrées, sorties vers chantier (imputées en coût réel), transferts, retours, pertes et ajustements."
        >
            <Button v-if="can('stocks.create')" @click="createOpen = true"
                ><Plus /> Nouveau mouvement</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un mouvement…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouveau mouvement de stock"
            description="Les pertes et ajustements exigent un motif et une validation (RG9.3)."
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
