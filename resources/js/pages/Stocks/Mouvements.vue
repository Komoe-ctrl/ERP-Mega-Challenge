<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, number } from '@/lib/format';
import { stocksNav } from '@/lib/navs';
import type { Tone } from '@/lib/status';
import { stockMovements } from '@/mocks/v2';
import type { StockMovement } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stocks', href: '/stocks' },
            { title: 'Mouvements', href: '/stocks/mouvements' },
        ],
    },
});

const { can } = usePermissions();
const lossOpen = ref(false);

const types: Record<StockMovement['type'], { label: string; tone: Tone }> = {
    entree: { label: 'Entrée', tone: 'success' },
    sortie: { label: 'Sortie', tone: 'info' },
    transfert: { label: 'Transfert', tone: 'brand' },
    inventaire: { label: 'Inventaire', tone: 'neutral' },
    retour: { label: 'Retour', tone: 'warning' },
    perte: { label: 'Perte', tone: 'danger' },
    ajustement: { label: 'Ajustement', tone: 'warning' },
};

const columns: DataTableColumn<StockMovement>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'type',
        header: 'Opération',
        filterFn: 'equalsString',
        cell: ({ row }) => h(StatusBadge, types[row.original.type]),
    },
    { accessorKey: 'label', header: 'Article' },
    {
        accessorKey: 'quantity',
        header: 'Quantité',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                `${number(row.original.quantity)} ${row.original.unit}`,
            ),
    },
    {
        id: 'route',
        header: 'De → vers',
        accessorFn: (m) => `${m.from} ${m.to}`,
        cell: ({ row }) => `${row.original.from} → ${row.original.to}`,
    },
    { accessorKey: 'author', header: 'Par' },
    {
        accessorKey: 'reason',
        header: 'Motif',
        cell: ({ row }) => row.original.reason ?? '—',
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'type',
        label: 'Opération',
        options: Object.entries(types).map(([value, t]) => ({
            value,
            label: t.label,
        })),
    },
];

function declareLoss(reason: string) {
    toast.success(
        `Perte déclarée, en attente de validation : « ${reason} » (maquette). Visible dans le journal des activités.`,
    );
}
</script>

<template>
    <Head title="Mouvements de stock" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Mouvements de stock"
            description="Entrées, sorties vers chantier (imputées en coût réel), transferts, retours, pertes et ajustements."
        >
            <Button
                v-if="can('stocks.create')"
                variant="outline"
                @click="lossOpen = true"
                >Déclarer une perte</Button
            >
            <Button
                v-if="can('stocks.create')"
                @click="toast.success('Nouveau mouvement (maquette).')"
                ><Plus /> Nouveau mouvement</Button
            >
        </PageHeader>
        <SubNav :links="stocksNav" />
        <DataTable
            :columns="columns"
            :data="stockMovements"
            :filters="filters"
            search-placeholder="Rechercher un mouvement…"
        />

        <ConfirmDialog
            v-model:open="lossOpen"
            title="Déclarer une perte"
            description="Les pertes et ajustements exigent un motif et une validation (RG9.3)."
            reason-label="Article, quantité et motif"
            confirm-label="Déclarer"
            require-reason
            destructive
            @confirm="declareLoss"
        />
    </div>
</template>
