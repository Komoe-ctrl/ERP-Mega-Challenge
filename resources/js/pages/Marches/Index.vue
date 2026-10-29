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
import { contractTotals, quoteTotals } from '@/lib/billing';
import { amountCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { contracts, quotes } from '@/mocks/v3';
import type { Contract } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Marchés', href: '/marches' }] },
});

const { can } = usePermissions();
const list = ref<Contract[]>(
    contracts.map((c) => ({ ...c, amendments: [...c.amendments] })),
);
const createOpen = ref(false);

type Row = Contract & { updated: number; days: number };
const rows = computed<Row[]>(() =>
    list.value.map((c) => ({
        ...c,
        updated: contractTotals(c).updatedAmount,
        days: contractTotals(c).contractualDays,
    })),
);

// RG6.1 : un marché naît d'un devis validé ; son montant initial reprend celui du devis.
const signable = computed(() =>
    quotes.filter(
        (q) =>
            q.status === 'valide' &&
            !list.value.some((c) =>
                c.projectName.startsWith(q.projectName.split(' — ')[0]),
            ),
    ),
);
const fields = computed<FieldDef[]>(() => [
    {
        name: 'quote',
        label: 'Devis validé',
        type: 'select',
        required: true,
        wide: true,
        options: signable.value.map((q) => ({
            value: q.id,
            label: `${q.number} — ${q.clientName} (${fcfa(quoteTotals(q).ht)} HT)`,
        })),
        hint: signable.value.length
            ? undefined
            : 'Aucun devis validé sans marché.',
    },
    {
        name: 'signedAt',
        label: 'Date de signature',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) > TODAY
                ? 'La signature ne peut pas être dans le futur.'
                : null,
    },
    {
        name: 'deadline',
        label: 'Délai contractuel',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'jours',
    },
    {
        name: 'advance',
        label: 'Taux d’acompte',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
    {
        name: 'retention',
        label: 'Retenue de garantie',
        type: 'number',
        required: true,
        min: 0,
        max: 100,
        step: 0.5,
        unit: '%',
    },
    {
        name: 'paymentDays',
        label: 'Délai de paiement',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        unit: 'jours',
    },
]);

function create(v: FieldValues) {
    const q = quotes.find((x) => x.id === Number(v.quote));

    if (!q) {
        return;
    }

    list.value.unshift({
        id: Date.now(),
        number: `MAR-2026-00${list.value.length + 1}`,
        clientName: q.clientName,
        projectName: q.projectName,
        initialAmount: quoteTotals(q).ht,
        signedAt: String(v.signedAt),
        deadlineDays: Number(v.deadline),
        advanceRate: Number(v.advance),
        retentionRate: Number(v.retention),
        paymentDays: Number(v.paymentDays),
        amendments: [],
    });
    toast.success(
        'Marché créé : le montant initial reprend celui du devis (maquette).',
    );
}

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Marché',
        cell: ({ row }) =>
            linkCell(
                `/marches/${row.original.id}`,
                row.original.number,
                row.original.projectName,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    {
        accessorKey: 'signedAt',
        header: 'Signé le',
        cell: ({ row }) => dateFr(row.original.signedAt),
    },
    {
        accessorKey: 'initialAmount',
        header: 'Montant initial',
        cell: ({ row }) => amountCell(fcfa(row.original.initialAmount)),
    },
    {
        accessorKey: 'updated',
        header: 'Montant actualisé',
        cell: ({ row }) => amountCell(fcfa(row.original.updated)),
    },
    {
        accessorKey: 'days',
        header: 'Délai',
        cell: ({ row }) => `${row.original.days} jours`,
    },
];
</script>

<template>
    <Head title="Marchés et contrats" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Marchés et contrats"
            description="Un marché naît d’un devis validé ou d’un appel d’offres gagné ; ses avenants modifient le montant et le délai."
        >
            <Button v-if="can('marches.create')" @click="createOpen = true"
                ><Plus /> Nouveau marché</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            search-placeholder="Rechercher un marché…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouveau marché"
            :initial="{ advance: 10, retention: 5, paymentDays: 30 }"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
