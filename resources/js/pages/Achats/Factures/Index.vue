<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Plus, X } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import SupplierInvoiceController from '@/actions/App/Http/Controllers/Achats/SupplierInvoiceController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { amountCell, badgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { SupplierInvoice } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

type Row = SupplierInvoice & { canControl: boolean; canPay: boolean };

const props = defineProps<{
    invoices: Paginated<Row>;
    filters: { status: string };
    canCreate: boolean;
    orders: {
        value: string;
        label: string;
        receptions: { value: string; label: string }[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Factures fournisseurs', href: '/achats/factures' },
        ],
    },
});

const createOpen = ref(false);

const mark = (ok: boolean) =>
    h(ok ? Check : X, {
        class: ok ? 'size-4 text-success' : 'size-4 text-danger',
        'aria-label': ok ? 'Concorde' : 'Écart',
    });

const fields = computed<FieldDef[]>(() => [
    {
        name: 'number',
        label: 'N° de facture fournisseur',
        required: true,
        minLength: 3,
    },
    {
        name: 'purchase_order_id',
        label: 'Bon de commande',
        type: 'select',
        required: true,
        options: props.orders.map((o) => ({ value: o.value, label: o.label })),
    },
    {
        name: 'reception_id',
        label: 'Réception rapprochée',
        type: 'select',
        options: props.orders.flatMap((o) =>
            o.receptions.map((r) => ({
                value: r.value,
                label: `${r.label} (${o.label.split(' — ')[0]})`,
            })),
        ),
        hint: 'Facultatif : sans réception, la facture ne concorde pas et reste non payable (RG8.10).',
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
    {
        name: 'invoice_date',
        label: 'Date de facture',
        type: 'date',
        required: true,
    },
    {
        name: 'due_date',
        label: 'Échéance',
        type: 'date',
        required: true,
    },
]);

function create(v: FieldValues) {
    router.post(
        SupplierInvoiceController.store.url(),
        {
            number: v.number,
            purchase_order_id: v.purchase_order_id,
            reception_id: v.reception_id || null,
            amount: v.amount,
            invoice_date: v.invoice_date,
            due_date: v.due_date,
        },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Facture invalide.'),
        },
    );
}

const act = (url: string) => router.post(url, {}, { preserveScroll: true });

function applyStatus(status: string) {
    router.get(
        SupplierInvoiceController.index.url(),
        status ? { status } : {},
        { preserveState: true, replace: true },
    );
}

const columns: DataTableColumn<Row>[] = [
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
        cell: ({ row }) =>
            `${row.original.orderNumber} · ${row.original.receptionNumber || '—'}`,
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
        cell: ({ row }) => badgeCell('invoice', row.original.status),
    },
    {
        id: 'actions',
        header: '',
        enableSorting: false,
        cell: ({ row }) => {
            const i = row.original;

            if (i.canControl && i.status === 'a_controler') {
                return h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        onClick: () =>
                            act(SupplierInvoiceController.control.url(i.id)),
                    },
                    () => 'Contrôler',
                );
            }

            if (i.canPay && i.status === 'controlee') {
                return h(
                    Button,
                    {
                        size: 'sm',
                        onClick: () =>
                            act(SupplierInvoiceController.pay.url(i.id)),
                    },
                    () => 'Payer',
                );
            }

            return null;
        },
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
            <Button v-if="canCreate" @click="createOpen = true"
                ><Plus /> Saisir une facture</Button
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
                v-for="(meta, value) in statuses.invoice"
                :key="value"
                :value="value"
            >
                {{ meta.label }}
            </option>
        </select>

        <DataTable
            :columns="columns"
            :data="invoices.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucune facture."
        />
        <PaginationBar :page="invoices" />

        <FormDialog
            v-model:open="createOpen"
            title="Saisir une facture fournisseur"
            description="Le contrôle à trois voies est calculé au contrôle de la facture."
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
