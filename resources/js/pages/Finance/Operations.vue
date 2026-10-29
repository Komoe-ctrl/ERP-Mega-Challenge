<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { financeNav } from '@/lib/navs';
import { bankBalance, cashBalance, cashEntries } from '@/mocks/v4';
import type { CashEntry } from '@/types/erp';

const props = defineProps<{ account: 'caisse' | 'banque' }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Finance', href: '/finance' }] },
});

const { can } = usePermissions();
const isCash = computed(() => props.account === 'caisse');
const entries = computed(() =>
    cashEntries.filter((e) => e.account === props.account),
);
const balance = computed(() => (isCash.value ? cashBalance : bankBalance));

const columns: DataTableColumn<CashEntry>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    { accessorKey: 'label', header: 'Libellé' },
    {
        accessorKey: 'chantier',
        header: 'Chantier',
        cell: ({ row }) => row.original.chantier ?? '—',
    },
    {
        accessorKey: 'kind',
        header: 'Sens',
        filterFn: 'equalsString',
        cell: ({ row }) =>
            toneBadgeCell(
                row.original.kind === 'recette' ? 'Recette' : 'Dépense',
                row.original.kind === 'recette' ? 'success' : 'warning',
            ),
    },
    { accessorKey: 'receipt', header: 'Justificatif' },
    {
        accessorKey: 'amount',
        header: 'Montant',
        cell: ({ row }) =>
            amountCell(
                `${row.original.kind === 'recette' ? '+' : '−'}${fcfa(row.original.amount)}`,
            ),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'kind',
        label: 'Sens',
        options: [
            { value: 'recette', label: 'Recettes' },
            { value: 'depense', label: 'Dépenses' },
        ],
    },
];
</script>

<template>
    <Head :title="isCash ? 'Caisse' : 'Banque'" />

    <div class="space-y-4 p-4">
        <PageHeader
            :title="isCash ? 'Caisse' : 'Banque'"
            :description="
                isCash
                    ? 'Chaque opération exige un justificatif ; le solde de caisse ne peut pas devenir négatif (RG15.4).'
                    : 'Relevés importés et rapprochement avec les opérations enregistrées (RG15.3).'
            "
        >
            <Button
                v-if="!isCash"
                variant="outline"
                @click="toast.success('Import du relevé bancaire (maquette).')"
                >Importer un relevé</Button
            >
            <Button
                v-if="can('finance.create')"
                @click="toast.success('Nouvelle opération (maquette).')"
                ><Plus /> Nouvelle opération</Button
            >
        </PageHeader>
        <SubNav :links="financeNav" />

        <div class="grid gap-3 sm:grid-cols-2">
            <KpiCard
                :label="isCash ? 'Solde de caisse' : 'Solde bancaire'"
                :value="fcfa(balance)"
                tone="info"
            />
            <KpiCard
                label="Opérations enregistrées"
                :value="String(entries.length)"
            />
        </div>

        <DataTable
            :columns="columns"
            :data="entries"
            :filters="filters"
            search-placeholder="Rechercher une opération…"
        />
    </div>
</template>
