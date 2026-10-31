<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import MovementController from '@/actions/App/Http/Controllers/Stocks/MovementController';
import DataTable from '@/components/DataTable.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, number } from '@/lib/format';
import type { Tone } from '@/lib/status';
import type { Paginated } from '@/types';
import type { FieldDef, FieldValues } from '@/types/forms';

type Movement = {
    id: number;
    date: string;
    type: string;
    item: string;
    unit: string;
    quantity: number;
    direction: string;
    warehouse: string;
    destination: string | null;
    chantier: string | null;
    reason: string | null;
    status: 'valide' | 'en_attente' | 'rejete';
    author: string;
    unitCost: number;
    canDecide: boolean;
};

const props = defineProps<{
    movements: Paginated<Movement>;
    filters: { type: string; magasin: string };
    warehouses: { id: number; name: string; type: string }[];
    articles: { id: number; label: string; unit: string }[];
    chantiers: { id: number; name: string }[];
    types: { value: string; label: string }[];
    can: { record: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stocks', href: '/stocks' },
            { title: 'Mouvements', href: '/stocks/mouvements' },
        ],
    },
});

const createOpen = ref(false);
const rejecting = ref<Movement | null>(null);

const tones: Record<string, Tone> = {
    entree: 'success',
    sortie: 'info',
    transfert: 'brand',
    inventaire: 'neutral',
    retour: 'warning',
    perte: 'danger',
    ajustement: 'warning',
};
const typeLabel = (value: string): string =>
    props.types.find((t) => t.value === value)?.label ??
    (value === 'inventaire' ? 'Inventaire' : value);

const statusMeta = {
    valide: { label: 'Validé', tone: 'success' as Tone },
    en_attente: { label: 'En attente de validation', tone: 'warning' as Tone },
    rejete: { label: 'Rejeté', tone: 'danger' as Tone },
};

const warehouseOptions = computed(() =>
    props.warehouses.map((w) => ({ value: String(w.id), label: w.name })),
);

const fields = computed<FieldDef[]>(() => [
    {
        name: 'type',
        label: 'Opération',
        type: 'select',
        required: true,
        options: props.types,
    },
    {
        name: 'article_id',
        label: 'Article',
        type: 'select',
        required: true,
        options: props.articles.map((a) => ({
            value: String(a.id),
            label: `${a.label} (${a.unit})`,
        })),
        wide: true,
    },
    {
        name: 'quantity',
        label: 'Quantité',
        type: 'number',
        required: true,
        min: 0.001,
        step: 0.001,
        hint: 'Quantité décimale acceptée (m³, kg…).',
    },
    {
        name: 'warehouse_id',
        label: 'Magasin',
        type: 'select',
        required: true,
        options: warehouseOptions.value,
    },
    {
        name: 'destination_warehouse_id',
        label: 'Destination (transfert)',
        type: 'select',
        options: warehouseOptions.value,
        validate: (v, values) =>
            values.type === 'transfert' && (!v || v === values.warehouse_id)
                ? 'Choisissez un magasin de destination différent.'
                : null,
    },
    {
        name: 'chantier_id',
        label: 'Chantier (sortie ou retour)',
        type: 'select',
        options: props.chantiers.map((c) => ({
            value: String(c.id),
            label: c.name,
        })),
        validate: (v, values) =>
            values.type === 'sortie' && !v
                ? 'Une sortie est imputée à un chantier (coût réel, RG9.2).'
                : null,
    },
    {
        name: 'unit_cost',
        label: 'Coût unitaire (entrée)',
        type: 'number',
        min: 1,
        step: 1,
        unit: 'FCFA',
        validate: (v, values) =>
            values.type === 'entree' && !v
                ? 'Indiquez le coût unitaire : le stock est valorisé au coût moyen pondéré.'
                : null,
    },
    {
        name: 'direction',
        label: 'Sens (ajustement)',
        type: 'select',
        options: [
            { value: 'in', label: 'Augmente le stock' },
            { value: 'out', label: 'Diminue le stock' },
        ],
        validate: (v, values) =>
            values.type === 'ajustement' && !v
                ? 'Indiquez si l’ajustement augmente ou diminue le stock.'
                : null,
    },
    {
        name: 'reason',
        label: 'Motif',
        type: 'textarea',
        wide: true,
        validate: (v, values) =>
            ['perte', 'ajustement'].includes(String(values.type)) &&
            !String(v ?? '').trim()
                ? 'Un motif est obligatoire pour une perte ou un ajustement (RG9.3).'
                : null,
    },
]);

function create(v: FieldValues) {
    router.post(
        MovementController.store.url(),
        Object.fromEntries(
            Object.entries(v).filter(
                ([, value]) => value !== '' && value !== null,
            ),
        ) as Record<string, string | number>,
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Mouvement refusé.'),
        },
    );
}

const act = (url: string) => router.post(url, {}, { preserveScroll: true });

function reject(reason: string) {
    if (!rejecting.value) {
        return;
    }

    router.post(
        MovementController.reject.url(rejecting.value.id),
        { reason },
        { preserveScroll: true },
    );
}

function applyFilters(changes: Partial<{ type: string; magasin: string }>) {
    const next = { ...props.filters, ...changes };

    router.get(
        MovementController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

const columns: DataTableColumn<Movement>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'type',
        header: 'Opération',
        cell: ({ row }) =>
            h(StatusBadge, {
                label: typeLabel(row.original.type),
                tone: tones[row.original.type] ?? 'neutral',
            }),
    },
    { accessorKey: 'item', header: 'Article' },
    {
        accessorKey: 'quantity',
        header: 'Quantité',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                `${row.original.direction === 'in' ? '+' : '−'}${number(row.original.quantity, row.original.quantity % 1 ? 3 : 0)} ${row.original.unit}`,
            ),
    },
    {
        id: 'route',
        header: 'De → vers',
        cell: ({ row }) =>
            `${row.original.warehouse} → ${row.original.destination ?? (row.original.chantier ? `Chantier ${row.original.chantier}` : '—')}`,
    },
    { accessorKey: 'author', header: 'Par' },
    {
        accessorKey: 'status',
        header: 'État',
        cell: ({ row }) => h(StatusBadge, statusMeta[row.original.status]),
    },
    {
        accessorKey: 'reason',
        header: 'Motif',
        cell: ({ row }) => row.original.reason ?? '—',
    },
    {
        id: 'actions',
        header: '',
        enableSorting: false,
        cell: ({ row }) =>
            row.original.canDecide
                ? h('div', { class: 'flex gap-2' }, [
                      h(
                          Button,
                          {
                              size: 'sm',
                              onClick: () =>
                                  act(
                                      MovementController.validateMovement.url(
                                          row.original.id,
                                      ),
                                  ),
                          },
                          () => 'Valider',
                      ),
                      h(
                          Button,
                          {
                              size: 'sm',
                              variant: 'outline',
                              onClick: () => (rejecting.value = row.original),
                          },
                          () => 'Rejeter',
                      ),
                  ])
                : null,
    },
];
</script>

<template>
    <Head title="Mouvements de stock" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Mouvements de stock"
            description="Entrées, sorties vers chantier (imputées en coût réel), transferts, retours, pertes et ajustements."
        >
            <Button v-if="can.record" @click="createOpen = true"
                ><Plus /> Nouveau mouvement</Button
            >
        </PageHeader>

        <div class="flex flex-wrap gap-3">
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par opération"
                :value="filters.type"
                @change="
                    applyFilters({
                        type: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Opération : toutes</option>
                <option v-for="t in types" :key="t.value" :value="t.value">
                    {{ t.label }}
                </option>
            </select>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par magasin"
                :value="filters.magasin"
                @change="
                    applyFilters({
                        magasin: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Magasin : tous</option>
                <option
                    v-for="w in warehouses"
                    :key="w.id"
                    :value="String(w.id)"
                >
                    {{ w.name }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="movements.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun mouvement."
        />
        <PaginationBar :page="movements" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouveau mouvement de stock"
            description="Les pertes et ajustements exigent un motif et une validation (RG9.3)."
            :fields="fields"
            @submit="create"
        />
        <ConfirmDialog
            :open="rejecting !== null"
            title="Rejeter le mouvement"
            description="Le stock reste inchangé."
            confirm-label="Rejeter"
            require-reason
            destructive
            @update:open="(v: boolean) => (v ? null : (rejecting = null))"
            @confirm="reject"
        />
    </div>
</template>
