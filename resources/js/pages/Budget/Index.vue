<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { h } from 'vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { consumptionTone } from '@/lib/status';
import { budgetLevel, budgetOf, budgetTotals } from '@/mocks/achats';
import { chantiers } from '@/mocks/chantiers';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Budget', href: '/budget' }] },
});

const rows = chantiers
    .filter((c) => budgetOf(c.id).length)
    .map((c) => {
        const t = budgetTotals(budgetOf(c.id));

        return {
            id: c.id,
            name: c.name,
            code: c.code,
            ...t,
            level: budgetLevel(t.consumption),
        };
    });

type Row = (typeof rows)[number];

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Chantier',
        cell: ({ row }) =>
            linkCell(
                `/budget/${row.original.id}`,
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
            description="Déboursé sec, engagements et reste à engager. Avertissement à 80 %, alerte critique à 100 %."
        />
        <DataTable :columns="columns" :data="rows" :searchable="false" />
    </div>
</template>
