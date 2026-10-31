<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Achats/PurchaseOrderController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { PurchaseOrder } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{
    orders: Paginated<PurchaseOrder>;
    filters: { status: string };
    orderable: {
        id: number;
        number: string;
        chantierName: string;
        total: number;
    }[];
    suppliers: { value: string; label: string }[];
    threshold: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Bons de commande', href: '/achats/commandes' },
        ],
    },
});

const { can } = usePermissions();
const createOpen = ref(false);

// RG8.8 : un bon regroupe des demandes validées d'un même chantier (ici une demande à la fois).
const fields = computed<FieldDef[]>(() => [
    {
        name: 'purchase_request_id',
        label: 'Demande validée',
        type: 'select',
        required: true,
        wide: true,
        options: props.orderable.map((r) => ({
            value: String(r.id),
            label: `${r.number} — ${r.chantierName} (${fcfa(r.total)})`,
        })),
        hint: props.orderable.length
            ? `Au-delà de ${fcfa(props.threshold)}, passez par une consultation (RG8.11).`
            : 'Aucune demande validée en attente de commande.',
    },
    {
        name: 'supplier_id',
        label: 'Fournisseur',
        type: 'select',
        required: true,
        options: props.suppliers,
        wide: true,
    },
]);

function create(v: FieldValues) {
    router.post(
        PurchaseOrderController.store.url(),
        {
            purchase_request_id: v.purchase_request_id,
            supplier_id: v.supplier_id,
        },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Commande refusée.'),
        },
    );
}

function applyStatus(status: string) {
    router.get(PurchaseOrderController.index.url(), status ? { status } : {}, {
        preserveState: true,
        replace: true,
    });
}

const columns: DataTableColumn<PurchaseOrder>[] = [
    {
        accessorKey: 'number',
        header: 'Bon de commande',
        cell: ({ row }) =>
            linkCell(
                PurchaseOrderController.show.url(row.original.id),
                row.original.number,
                row.original.requestNumbers.join(', '),
            ),
    },
    { accessorKey: 'supplierName', header: 'Fournisseur' },
    { accessorKey: 'chantierName', header: 'Chantier' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'amount',
        header: 'Montant',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('order', row.original.status),
    },
];
</script>

<template>
    <Head title="Bons de commande" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Bons de commande"
            description="Un bon peut regrouper plusieurs demandes d’un même chantier ; une demande peut être servie par plusieurs commandes (RG8.8)."
        >
            <Button v-if="can('achats.create')" @click="createOpen = true"
                ><Plus /> Nouveau bon de commande</Button
            >
        </PageHeader>

        <select
            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
            aria-label="Filtrer par statut"
            :value="filters.status"
            @change="applyStatus(($event.target as HTMLSelectElement).value)"
        >
            <option value="">Statut : tous</option>
            <option
                v-for="(meta, value) in statuses.order"
                :key="value"
                :value="value"
            >
                {{ meta.label }}
            </option>
        </select>

        <DataTable
            :columns="columns"
            :data="orders.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun bon de commande."
        />
        <PaginationBar :page="orders" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouveau bon de commande"
            :fields="fields"
            submit-label="Émettre"
            @submit="create"
        />
    </div>
</template>
