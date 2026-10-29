<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { statuses } from '@/lib/status';
import { requestTotal, requests } from '@/mocks/achats';
import { orders, suppliers } from '@/mocks/v2';
import type { PurchaseOrder } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Bons de commande', href: '/achats/commandes' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<PurchaseOrder[]>(orders.map((o) => ({ ...o })));
const createOpen = ref(false);

// RG8.8 : un bon regroupe des demandes validées d'un même chantier (ici une demande à la fois).
const orderable = computed(() =>
    requests.filter(
        (r) =>
            r.status === 'validee' &&
            !rows.value.some((o) => o.requestNumbers.includes(r.number)),
    ),
);
const fields = computed<FieldDef[]>(() => [
    {
        name: 'request',
        label: 'Demande validée',
        type: 'select',
        required: true,
        wide: true,
        options: orderable.value.map((r) => ({
            value: r.number,
            label: `${r.number} — ${r.chantierName} (${fcfa(requestTotal(r))})`,
        })),
        hint: orderable.value.length
            ? undefined
            : 'Aucune demande validée en attente de commande.',
    },
    {
        name: 'supplier',
        label: 'Fournisseur',
        type: 'select',
        required: true,
        options: suppliers.map((s) => ({ value: s.name, label: s.name })),
        wide: true,
    },
    {
        name: 'delivery',
        label: 'Livraison souhaitée',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) < TODAY
                ? 'La date ne peut pas être dans le passé.'
                : null,
    },
]);

function create(v: FieldValues) {
    const request = requests.find((r) => r.number === v.request);

    if (!request) {
        return;
    }

    rows.value.unshift({
        id: Date.now(),
        number: `BC-2026-00${114 + rows.value.length - orders.length}`,
        supplierName: String(v.supplier),
        chantierName: request.chantierName,
        requestNumbers: [request.number],
        date: TODAY,
        amount: requestTotal(request),
        status: 'emis',
        lines: request.lines.map((l) => ({
            label: `Ligne ${l.id}`,
            unit: '',
            ordered: l.quantity,
            received: 0,
            unitPrice: 0,
        })),
    });
    toast.success('Bon de commande émis (maquette).');
}

const columns: DataTableColumn<PurchaseOrder>[] = [
    {
        accessorKey: 'number',
        header: 'Bon de commande',
        cell: ({ row }) =>
            linkCell(
                `/achats/commandes/${row.original.id}`,
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
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('order', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.order).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
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
        <SubNav :links="achatsNav" />
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un bon de commande…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouveau bon de commande"
            :fields="fields"
            submit-label="Émettre"
            @submit="create"
        />
    </div>
</template>
