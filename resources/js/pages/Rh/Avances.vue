<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { advances } from '@/mocks/v4';
import type { Advance } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ressources humaines', href: '/rh' },
            { title: 'Avances', href: '/rh/avances' },
        ],
    },
});

const { can } = usePermissions();

const columns: DataTableColumn<Advance>[] = [
    { accessorKey: 'employee', header: 'Employé' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'amount',
        header: 'Avance',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        accessorKey: 'deducted',
        header: 'Déjà déduit',
        cell: ({ row }) => amountCell(fcfa(row.original.deducted)),
    },
    {
        id: 'left',
        header: 'Reste à déduire',
        accessorFn: (a) => a.amount - a.deducted,
        cell: ({ row }) =>
            amountCell(fcfa(row.original.amount - row.original.deducted)),
    },
];
</script>

<template>
    <Head title="Avances sur salaire" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Avances sur salaire"
            description="Les avances sont déduites automatiquement de la paie suivante (RG13.3)."
        >
            <Button
                v-if="can('rh.create')"
                @click="toast.success('Nouvelle avance (maquette).')"
                ><Plus /> Enregistrer une avance</Button
            >
        </PageHeader>
        <SubNav :links="rhNav" />
        <DataTable :columns="columns" :data="advances" :searchable="false" />
    </div>
</template>
