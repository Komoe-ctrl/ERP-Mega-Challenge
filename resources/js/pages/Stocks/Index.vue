<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Boxes, Wallet } from '@lucide/vue';
import { h } from 'vue';
import DataTable from '@/components/DataTable.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { amountCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa, fcfaShort, number } from '@/lib/format';
import { stockItems } from '@/mocks/v2';
import type { StockItem } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stocks', href: '/stocks' }] },
});

const low = stockItems.filter((s) => s.quantity < s.minimum);
// RG9.5 : valorisation au coût moyen pondéré.
const valuation = stockItems.reduce(
    (sum, s) => sum + s.quantity * s.averageCost,
    0,
);

const columns: DataTableColumn<StockItem>[] = [
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
        cell: ({ row }) => amountCell(number(row.original.minimum)),
    },
    {
        accessorKey: 'averageCost',
        header: 'Coût moyen',
        cell: ({ row }) => amountCell(fcfa(row.original.averageCost)),
    },
    {
        id: 'value',
        header: 'Valeur',
        accessorFn: (s) => s.quantity * s.averageCost,
        cell: ({ row }) =>
            amountCell(fcfa(row.original.quantity * row.original.averageCost)),
    },
    {
        id: 'alert',
        header: 'État',
        accessorFn: (s) => (s.quantity < s.minimum ? 'bas' : 'ok'),
        filterFn: 'equalsString',
        cell: ({ row }) =>
            row.original.quantity < row.original.minimum
                ? h(StatusBadge, {
                      label: 'À réapprovisionner',
                      tone: 'danger',
                  })
                : h(StatusBadge, { label: 'Suffisant', tone: 'success' }),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'warehouse',
        label: 'Magasin',
        options: [...new Set(stockItems.map((s) => s.warehouse))].map((w) => ({
            value: w,
            label: w,
        })),
    },
    {
        columnId: 'family',
        label: 'Famille',
        options: [...new Set(stockItems.map((s) => s.family))].map((f) => ({
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
                :value="fcfaShort(valuation)"
                hint="Coût moyen pondéré"
                :icon="Wallet"
                tone="info"
            />
            <KpiCard
                label="Références en stock"
                :value="String(stockItems.length)"
                :icon="Boxes"
                tone="success"
            />
            <KpiCard
                label="Sous le seuil minimal"
                :value="String(low.length)"
                :hint="
                    low
                        .map((l) => l.label)
                        .slice(0, 2)
                        .join(', ')
                "
                :icon="AlertTriangle"
                :tone="low.length ? 'danger' : 'success'"
            />
        </div>

        <DataTable
            :columns="columns"
            :data="stockItems"
            :filters="filters"
            search-placeholder="Rechercher un article…"
        />
    </div>
</template>
