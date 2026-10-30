<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { h } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/Budget/BudgetController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { consumptionTone } from '@/lib/status';

type Row = {
    id: number;
    code: string;
    name: string;
    initial: number;
    revised: number;
    committed: number;
    actual: number;
    remaining: number;
    consumption: number;
    level: string;
};

defineProps<{ rows: Row[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Budget', href: '/budget' }] },
});

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Chantier',
        cell: ({ row }) =>
            linkCell(
                BudgetController.show.url(row.original.id),
                row.original.name,
                row.original.code,
            ),
    },
    {
        accessorKey: 'initial',
        header: 'Initial (DS)',
        cell: ({ row }) => amountCell(fcfa(row.original.initial)),
    },
    {
        accessorKey: 'revised',
        header: 'Révisé',
        cell: ({ row }) => amountCell(fcfa(row.original.revised)),
    },
    {
        accessorKey: 'committed',
        header: 'Engagé',
        cell: ({ row }) => amountCell(fcfa(row.original.committed)),
    },
    {
        accessorKey: 'actual',
        header: 'Coût réel',
        cell: ({ row }) => amountCell(fcfa(row.original.actual)),
    },
    {
        accessorKey: 'remaining',
        header: 'Reste à engager',
        cell: ({ row }) => amountCell(fcfa(row.original.remaining)),
    },
    {
        accessorKey: 'consumption',
        header: 'Consommation',
        cell: ({ row }) =>
            h('div', { class: 'w-32' }, [
                h(ProgressBar, {
                    value: row.original.consumption,
                    tone: consumptionTone(row.original.consumption),
                }),
            ]),
    },
    {
        accessorKey: 'level',
        header: 'Seuil',
        cell: ({ row }) => badgeCell('budget', row.original.level),
    },
];
</script>

<template>
    <Head title="Budget" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Budget des chantiers"
            description="Déboursé sec, engagements et reste à engager. Avertissement à 80 %, alerte critique à 100 % (seuils paramétrables)."
        />
        <DataTable
            :columns="columns"
            :data="rows"
            :searchable="false"
            empty-message="Aucun chantier n’a encore de budget renseigné."
        />
    </div>
</template>
