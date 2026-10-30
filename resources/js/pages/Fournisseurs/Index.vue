<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Star } from '@lucide/vue';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierController from '@/actions/App/Http/Controllers/Fournisseurs/SupplierController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { linkCell, toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import type { Paginated } from '@/types';
import type { Supplier } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Filters = { q: string; category: string; state: string };

const props = defineProps<{
    suppliers: Paginated<Supplier>;
    filters: Filters;
    categories: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Fournisseurs', href: '/fournisseurs' }] },
});

const { can } = usePermissions();
const search = ref(props.filters.q);
const createOpen = ref(false);

function apply(changes: Partial<Filters>) {
    const next = { ...props.filters, ...changes };

    router.get(
        SupplierController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

const categoryOptions = props.categories.map((c) => ({ value: c, label: c }));

const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Raison sociale',
        required: true,
        minLength: 3,
        wide: true,
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
    { name: 'email', label: 'E-mail (facultatif)', type: 'email' },
    { name: 'city', label: 'Ville', required: true },
];

// Le serveur refait toute la validation (doublons, catégories) ; ses messages reviennent en notification.
function create(v: FieldValues) {
    const categories = [
        String(v.category),
        ...(v.category2 && v.category2 !== v.category
            ? [String(v.category2)]
            : []),
    ];

    router.post(
        SupplierController.store.url(),
        {
            name: v.name,
            contact: v.contact,
            phone: v.phone,
            email: v.email || null,
            city: v.city,
            categories,
        },
        { onError: (errors) => toast.error(Object.values(errors)[0]) },
    );
}

const columns: DataTableColumn<Supplier>[] = [
    {
        accessorKey: 'name',
        header: 'Fournisseur',
        cell: ({ row }) =>
            linkCell(
                SupplierController.show.url(row.original.id),
                row.original.name,
                row.original.city,
            ),
    },
    {
        id: 'categories',
        header: 'Catégories',
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
    {
        id: 'state',
        header: 'État',
        cell: ({ row }) =>
            toneBadgeCell(
                row.original.active === false ? 'Désactivé' : 'Actif',
                row.original.active === false ? 'neutral' : 'success',
            ),
    },
];
</script>

<template>
    <Head title="Fournisseurs" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Fournisseurs"
            description="Catégories, contacts, tarifs et, avec le processus d’achat, historique des commandes et évaluation."
        >
            <Button v-if="can('fournisseurs.create')" @click="createOpen = true"
                ><Plus /> Nouveau fournisseur</Button
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
                    placeholder="Rechercher un fournisseur, une ville…"
                    aria-label="Rechercher un fournisseur"
                />
            </form>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par catégorie"
                :value="filters.category"
                @change="
                    apply({
                        category: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Catégorie : toutes</option>
                <option v-for="c in categories" :key="c" :value="c">
                    {{ c }}
                </option>
            </select>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par état"
                :value="filters.state"
                @change="
                    apply({ state: ($event.target as HTMLSelectElement).value })
                "
            >
                <option value="">État : tous</option>
                <option value="actif">Actifs</option>
                <option value="inactif">Désactivés</option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="suppliers.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun fournisseur ne correspond à ces filtres."
        />
        <PaginationBar :page="suppliers" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouveau fournisseur"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
