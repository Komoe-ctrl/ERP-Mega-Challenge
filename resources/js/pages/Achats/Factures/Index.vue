<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { h } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { amountCell, badgeCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { achatsNav } from '@/lib/navs';
import { statuses } from '@/lib/status';
import { invoices } from '@/mocks/v2';
import type { SupplierInvoice } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Factures fournisseurs', href: '/achats/factures' },
        ],
    },
});

const mark = (ok: boolean) =>
    h(ok ? Check : X, {
        class: ok ? 'size-4 text-success' : 'size-4 text-danger',
        'aria-label': ok ? 'Concorde' : 'Écart',
    });

// RG8.10 : une facture n'est payable que si elle concorde avec le bon de commande et la réception.
const payable = (i: SupplierInvoice) =>
    i.match.order && i.match.reception && i.match.price;

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
            description="Contrôle à trois voies : facture, bon de commande et réception doivent concorder."
        />
        <SubNav :links="achatsNav" />
        <DataTable
            :columns="columns"
            :data="invoices"
            :filters="filters"
            search-placeholder="Rechercher une facture…"
        />
    </div>
</template>
