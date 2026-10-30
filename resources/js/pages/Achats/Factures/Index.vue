<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Plus, X } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { addDays, dateFr, fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { statuses } from '@/lib/status';
import { invoices, orders, receptions } from '@/mocks/v2';
import type { SupplierInvoice } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Factures fournisseurs', href: '/achats/factures' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<SupplierInvoice[]>(invoices.map((i) => ({ ...i })));
const createOpen = ref(false);

const mark = (ok: boolean) =>
    h(ok ? Check : X, {
        class: ok ? 'size-4 text-success' : 'size-4 text-danger',
        'aria-label': ok ? 'Concorde' : 'Écart',
    });

// RG8.10 : une facture n'est payable que si elle concorde avec le bon de commande et la réception.
const payable = (i: SupplierInvoice) =>
    i.match.order && i.match.reception && i.match.price;

const fields = computed<FieldDef[]>(() => [
    {
        name: 'number',
        label: 'N° de facture fournisseur',
        required: true,
        minLength: 3,
        validate: (v) =>
            rows.value.some((i) => i.number === v)
                ? 'Cette facture est déjà saisie.'
                : null,
    },
    {
        name: 'order',
        label: 'Bon de commande',
        type: 'select',
        required: true,
        options: orders.map((o) => ({
            value: o.number,
            label: `${o.number} — ${o.supplierName}`,
        })),
    },
    {
        name: 'amount',
        label: 'Montant facturé',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
    },
    { name: 'date', label: 'Date de facture', type: 'date', required: true },
    {
        name: 'file',
        label: 'Pièce jointe',
        type: 'file',
        hint: 'Facture scannée (20 Mo maximum).',
        wide: true,
    },
]);

// Contrôle à trois voies calculé à la saisie : bon de commande, réception, prix.
function create(v: FieldValues) {
    const order = orders.find((o) => o.number === v.order);

    if (!order) {
        return;
    }

    const reception = receptions.find((r) => r.orderNumber === order.number);
    const match = {
        order: true,
        reception: Boolean(reception) && reception?.gap === 'conforme',
        price: Number(v.amount) === order.amount,
    };

    rows.value.unshift({
        id: Date.now(),
        number: String(v.number),
        supplierName: order.supplierName,
        orderNumber: order.number,
        receptionNumber: reception?.number ?? '—',
        date: String(v.date),
        dueDate: addDays(String(v.date), 30),
        amount: Number(v.amount),
        match,
        status: match.reception && match.price ? 'controlee' : 'ecart',
    });
    toast[match.reception && match.price ? 'success' : 'warning'](
        match.reception && match.price
            ? 'Facture concordante : elle peut être payée.'
            : 'Écart de contrôle : paiement bloqué tant que la facture ne concorde pas.',
    );
}

const columns: DataTableColumn<SupplierInvoice>[] = [
    {
        accessorKey: 'number',
        header: 'Facture',
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-medium' }, row.original.number),
                h(
                    'div',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.supplierName,
                ),
            ]),
    },
    {
        id: 'refs',
        header: 'BC / réception',
        accessorFn: (i) => `${i.orderNumber} ${i.receptionNumber}`,
        cell: ({ row }) =>
            `${row.original.orderNumber} · ${row.original.receptionNumber}`,
    },
    {
        accessorKey: 'dueDate',
        header: 'Échéance',
        cell: ({ row }) => dateFr(row.original.dueDate),
    },
    {
        accessorKey: 'amount',
        header: 'Montant',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        id: 'bc',
        header: 'Bon de commande',
        enableSorting: false,
        cell: ({ row }) => mark(row.original.match.order),
    },
    {
        id: 'br',
        header: 'Réception',
        enableSorting: false,
        cell: ({ row }) => mark(row.original.match.reception),
    },
    {
        id: 'px',
        header: 'Prix',
        enableSorting: false,
        cell: ({ row }) => mark(row.original.match.price),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('invoice', row.original.status),
    },
    {
        id: 'actions',
        header: '',
        enableSorting: false,
        cell: ({ row }) =>
            row.original.status === 'payee'
                ? null
                : h(
                      Button,
                      {
                          size: 'sm',
                          variant: payable(row.original)
                              ? 'default'
                              : 'outline',
                          disabled: !payable(row.original),
                          title: payable(row.original)
                              ? 'Transmettre à la comptabilité'
                              : 'Facture non concordante : paiement bloqué',
                          onClick: () =>
                              toast.success(
                                  `${row.original.number} transmise à la comptabilité (maquette).`,
                              ),
                      },
                      () => 'Payer',
                  ),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.invoice).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Factures fournisseurs" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Factures fournisseurs"
            description="Contrôle à trois voies : facture, bon de commande et réception doivent concorder (RG8.10)."
        >
            <Button
                v-if="can('finance.create') || can('achats.create')"
                @click="createOpen = true"
                ><Plus /> Saisir une facture</Button
            >
        </PageHeader>
        <SubNav :links="achatsNav" />
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher une facture…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Saisir une facture fournisseur"
            description="Le contrôle à trois voies est calculé à l’enregistrement."
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
