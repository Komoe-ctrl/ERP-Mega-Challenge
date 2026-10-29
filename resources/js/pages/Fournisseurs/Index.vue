<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, Star } from '@lucide/vue';
import { h } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { suppliers } from '@/mocks/v2';
import type { Supplier } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fournisseurs', href: '/fournisseurs' }] },
});

const { can } = usePermissions();

const columns: DataTableColumn<Supplier>[] = [
    {
        accessorKey: 'name',
        header: 'Fournisseur',
        cell: ({ row }) =>
            linkCell(
                `/fournisseurs/${row.original.id}`,
                row.original.name,
                row.original.city,
            ),
    },
    {
        id: 'categories',
        header: 'Catégories',
        accessorFn: (s) => s.categories.join(', '),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                row.original.categories.map((c) =>
                    h(StatusBadge, { label: c, tone: 'neutral' }),
                ),
            ),
    },
    {
        accessorKey: 'contact',
        header: 'Contact',
        cell: ({ row }) => `${row.original.contact} · ${row.original.phone}`,
    },
    {
        accessorKey: 'rating',
        header: 'Note',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'inline-flex items-center gap-1 tabular-nums' },
                [
                    h(Star, { class: 'size-3.5 fill-brand text-brand' }),
                    `${row.original.rating.toFixed(1)}`.replace('.', ','),
                ],
            ),
    },
    { accessorKey: 'openOrders', header: 'Commandes en cours' },
    { accessorKey: 'unpaidInvoices', header: 'Factures impayées' },
];
</script>

<template>
    <Head title="Fournisseurs" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Fournisseurs"
            description="Catégories, contacts, historique des commandes et évaluation après chaque réception."
        >
            <Button
                v-if="can('fournisseurs.create')"
                @click="toast.success('Création d’un fournisseur (maquette).')"
                ><Plus /> Nouveau fournisseur</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="suppliers"
            search-placeholder="Rechercher un fournisseur…"
        />
    </div>
</template>
