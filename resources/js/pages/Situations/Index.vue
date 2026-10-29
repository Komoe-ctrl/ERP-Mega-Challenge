<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { computeSituation, contractTotals } from '@/lib/billing';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa, percent, TODAY } from '@/lib/format';
import { statuses } from '@/lib/status';
import { ca11Example, contracts, situations } from '@/mocks/v3';
import type { Situation } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Situations de travaux', href: '/situations' }],
    },
});

const { can } = usePermissions();
const list = ref<Situation[]>(situations.map((s) => ({ ...s })));
const createOpen = ref(false);

type Row = Situation & { amount: number; netTtc: number };
const rows = computed<Row[]>(() =>
    list.value.map((s) => ({
        ...s,
        amount: computeSituation(s).amount,
        netTtc: computeSituation(s).netTtc,
    })),
);
const example = computeSituation(ca11Example);

const lastOf = (contractNumber: string) =>
    list.value
        .filter((s) => s.contractNumber === contractNumber)
        .sort((a, b) => b.number - a.number)[0];

const fields = computed<FieldDef[]>(() => [
    {
        name: 'contract',
        label: 'Marché',
        type: 'select',
        required: true,
        wide: true,
        options: contracts.map((c) => ({
            value: c.number,
            label: `${c.number} — ${c.clientName}`,
        })),
    },
    {
        name: 'progress',
        label: 'Avancement cumulé',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
        validate: (v, values) => {
            const last = lastOf(String(values.contract));

            return last && Number(v) < last.progressCumul
                ? `L’avancement ne peut pas être inférieur à celui de la situation n°${last.number} (${last.progressCumul} %).`
                : null;
        },
    },
    {
        name: 'date',
        label: 'Date de la situation',
        type: 'date',
        required: true,
    },
]);

function create(v: FieldValues) {
    const contract = contracts.find((c) => c.number === v.contract);

    if (!contract) {
        return;
    }

    const last = lastOf(contract.number);

    list.value.push({
        id: Date.now(),
        contractNumber: contract.number,
        clientName: contract.clientName,
        number: (last?.number ?? 0) + 1,
        date: String(v.date),
        progressCumul: Number(v.progress),
        previousProgress: last?.progressCumul ?? 0,
        marketAmount: contractTotals(contract).updatedAmount,
        status: 'brouillon',
        retentionRate: contract.retentionRate,
        advanceRefundRate: contract.advanceRate,
        vatRate: 18,
    });
    toast.success(
        'Situation créée en brouillon : les taux viennent du marché (maquette).',
    );
}

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Situation',
        cell: ({ row }) =>
            linkCell(
                `/situations/${row.original.id}`,
                `n°${row.original.number}`,
                row.original.contractNumber,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'progressCumul',
        header: 'Avancement cumulé',
        cell: ({ row }) => percent(row.original.progressCumul),
    },
    {
        accessorKey: 'amount',
        header: 'Travaux de la période',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        accessorKey: 'netTtc',
        header: 'Net à payer TTC',
        cell: ({ row }) => amountCell(fcfa(row.original.netTtc)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('situation', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.situation).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Situations de travaux" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Situations de travaux"
            description="Factures intermédiaires calculées sur l’avancement réel. Une situation validée est figée ; une correction passe par la suivante (RG11.3)."
        >
            <Button v-if="can('situations.create')" @click="createOpen = true"
                ><Plus /> Nouvelle situation</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher une situation…"
        />

        <aside class="rounded-xl border bg-card p-4 text-sm shadow-xs">
            <h2 class="mb-1 font-display font-semibold">
                Vérification : exemple du cahier des charges (CA11)
            </h2>
            <p class="text-muted-foreground">
                Marché de 100 000 000 FCFA, avancement cumulé de 30 % à 40 %,
                retenue 5 %, remboursement d’acompte 10 %.
            </p>
            <p class="mt-1 tabular-nums">
                Situation : <strong>{{ fcfa(example.amount) }}</strong> −
                {{ fcfa(example.retention) }} −
                {{ fcfa(example.advanceRefund) }} = net à payer hors taxes
                <strong>{{ fcfa(example.netHt) }}</strong
                >.
            </p>
        </aside>

        <FormDialog
            v-model:open="createOpen"
            title="Nouvelle situation"
            :initial="{ date: TODAY }"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
