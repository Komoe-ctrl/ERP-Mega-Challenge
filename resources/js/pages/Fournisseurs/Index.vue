<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, Star } from '@lucide/vue';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { suppliers } from '@/mocks/v2';
import type { Supplier } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fournisseurs', href: '/fournisseurs' }] },
});

const { can } = usePermissions();

const supplierCategories = [
    'Matériaux',
    'Équipements',
    'Engins',
    'Transport',
    'Sous-traitance',
    'Prestations techniques',
];

const rows = ref<Supplier[]>(suppliers.map((s) => ({ ...s })));
const createOpen = ref(false);
const categoryOptions = supplierCategories.map((c) => ({ value: c, label: c }));

const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Raison sociale',
        required: true,
        minLength: 3,
        wide: true,
        validate: (v) =>
            rows.value.some(
                (s) => s.name.toLowerCase() === String(v).trim().toLowerCase(),
            )
                ? 'Ce fournisseur existe déjà.'
                : null,
    },
    {
        name: 'category',
        label: 'Catégorie principale',
        type: 'select',
        required: true,
        options: categoryOptions,
        hint: 'Seuls les fournisseurs de la bonne catégorie sont proposés en consultation (RG3.1).',
    },
    {
        name: 'category2',
        label: 'Seconde catégorie (facultatif)',
        type: 'select',
        options: categoryOptions,
    },
    { name: 'contact', label: 'Contact', required: true },
    {
        name: 'phone',
        label: 'Téléphone',
        type: 'tel',
        required: true,
        pattern: /^\+?[\d\s]{8,18}$/,
        patternMessage: 'Numéro invalide (ex. +225 07 00 00 00 00).',
    },
    { name: 'city', label: 'Ville', required: true },
];

function create(v: FieldValues) {
    const categories = [
        String(v.category),
        ...(v.category2 && v.category2 !== v.category
            ? [String(v.category2)]
            : []),
    ];
    rows.value.push({
        id: Date.now(),
        name: String(v.name),
        categories,
        contact: String(v.contact),
        phone: String(v.phone),
        city: String(v.city),
        rating: 0,
        openOrders: 0,
        unpaidInvoices: 0,
    });
    toast.success('Fournisseur créé (maquette).');
}

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
            row.original.rating
                ? h(
                      'span',
                      { class: 'inline-flex items-center gap-1 tabular-nums' },
                      [
                          h(Star, { class: 'size-3.5 fill-brand text-brand' }),
                          `${row.original.rating.toFixed(1)}`.replace('.', ','),
                      ],
                  )
                : h('span', { class: 'text-muted-foreground' }, 'Non évalué'),
    },
    { accessorKey: 'openOrders', header: 'Commandes en cours' },
    { accessorKey: 'unpaidInvoices', header: 'Factures impayées' },
];

const filters: DataTableFilter[] = [];
</script>

<template>
    <Head title="Fournisseurs" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Fournisseurs"
            description="Catégories, contacts, historique des commandes et évaluation après chaque réception."
        >
            <Button v-if="can('fournisseurs.create')" @click="createOpen = true"
                ><Plus /> Nouveau fournisseur</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un fournisseur, une catégorie…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouveau fournisseur"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
