<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Boxes, Wallet } from '@lucide/vue';
import { h } from 'vue';
import { toast } from 'vue-sonner';
import StockController from '@/actions/App/Http/Controllers/Stocks/StockController';
import DataTable from '@/components/DataTable.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { amountCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa, fcfaShort, number } from '@/lib/format';

type Item = {
    id: number;
    articleId: number;
    reference: string;
    label: string;
    family: string;
    unit: string;
    warehouse: string;
    warehouseId: number;
    quantity: number;
    minimum: number;
    averageCost: number;
    value: number;
    low: boolean;
};

const props = defineProps<{
    items: Item[];
    warehouses: { id: number; name: string; type: string }[];
    filters: { magasin: string };
    totals: { value: number; low: number; items: number };
    can: { configure: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stocks', href: '/stocks' }] },
});

function applyWarehouse(id: string) {
    router.get(StockController.index.url(), id ? { magasin: id } : {}, {
        preserveState: true,
        replace: true,
    });
}

// RG9.4 : le seuil minimal se règle par article et par magasin.
function setMinimum(item: Item, event: Event) {
    const value = (event.target as HTMLInputElement).value;

    router.put(
        StockController.setMinimum.url(item.id),
        { minimum: value === '' ? 0 : value },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Seuil invalide.'),
        },
    );
}

const columns: DataTableColumn<Item>[] = [
    {
        accessorKey: 'label',
        header: 'Article',
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-medium' }, row.original.label),
                h(
                    'div',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.reference,
                ),
            ]),
    },
    { accessorKey: 'warehouse', header: 'Magasin', filterFn: 'equalsString' },
    { accessorKey: 'family', header: 'Famille', filterFn: 'equalsString' },
    {
        accessorKey: 'quantity',
        header: 'Quantité',
        cell: ({ row }) =>
            amountCell(`${number(row.original.quantity)} ${row.original.unit}`),
    },
    {
        accessorKey: 'minimum',
        header: 'Seuil mini',
        cell: ({ row }) =>
            props.can.configure
                ? h('input', {
                      type: 'number',
                      min: 0,
                      step: 'any',
                      value: row.original.minimum,
                      class: 'h-8 w-24 rounded-md border bg-background px-2 text-right tabular-nums',
                      'aria-label': `Seuil minimal : ${row.original.label}`,
                      onChange: (e: Event) => setMinimum(row.original, e),
                  })
                : amountCell(number(row.original.minimum)),
    },
    {
        accessorKey: 'averageCost',
        header: 'Coût moyen',
        cell: ({ row }) => amountCell(fcfa(row.original.averageCost)),
    },
    {
        accessorKey: 'value',
        header: 'Valeur',
        cell: ({ row }) => amountCell(fcfa(row.original.value)),
    },
    {
        id: 'alert',
        header: 'État',
        accessorFn: (s) => (s.low ? 'bas' : 'ok'),
        filterFn: 'equalsString',
        cell: ({ row }) =>
            row.original.low
                ? h(StatusBadge, {
                      label: 'À réapprovisionner',
                      tone: 'danger',
                  })
                : h(StatusBadge, { label: 'Suffisant', tone: 'success' }),
    },
];

const tableFilters: DataTableFilter[] = [
    {
        columnId: 'family',
        label: 'Famille',
        options: [...new Set(props.items.map((s) => s.family))].map((f) => ({
            value: f,
            label: f,
        })),
    },
    {
        columnId: 'alert',
        label: 'État',
        options: [
            { value: 'bas', label: 'Sous le seuil' },
            { value: 'ok', label: 'Suffisant' },
        ],
    },
];
</script>

<template>
    <Head title="État des stocks" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="État des stocks"
            description="Un stock par magasin (dépôt central ou magasin de chantier), valorisé au coût moyen pondéré."
        />

        <div class="grid gap-3 sm:grid-cols-3">
            <KpiCard
                label="Valeur du stock"
                :value="fcfaShort(totals.value)"
                hint="Coût moyen pondéré"
                :icon="Wallet"
                tone="info"
            />
            <KpiCard
                label="Références en stock"
                :value="String(totals.items)"
                :icon="Boxes"
                tone="success"
            />
            <KpiCard
                label="Sous le seuil minimal"
                :value="String(totals.low)"
                :icon="AlertTriangle"
                :tone="totals.low ? 'danger' : 'success'"
            />
        </div>

        <select
            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
            aria-label="Filtrer par magasin"
            :value="filters.magasin"
            @change="applyWarehouse(($event.target as HTMLSelectElement).value)"
        >
            <option value="">Magasin : tous</option>
            <option v-for="w in warehouses" :key="w.id" :value="String(w.id)">
                {{ w.name }}
            </option>
        </select>

        <DataTable
            :columns="columns"
            :data="items"
            :filters="tableFilters"
            search-placeholder="Rechercher un article…"
        />
    </div>
</template>
