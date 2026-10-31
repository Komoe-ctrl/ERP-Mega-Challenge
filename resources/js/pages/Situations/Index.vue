<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import SituationController from '@/actions/App/Http/Controllers/Situations/SituationController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computeSituation } from '@/lib/billing';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa, percent, TODAY } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { FieldDef, FieldValues } from '@/types/forms';

type SituationRow = {
    id: number;
    number: number;
    contractNumber: string;
    clientName: string;
    date: string;
    status: string;
    progressCumul: number;
    amount: number;
    netTtc: number;
};

const props = defineProps<{
    situations: Paginated<SituationRow>;
    filters: { q: string; status: string };
    contracts: { id: number; label: string }[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Situations de travaux', href: '/situations' }],
    },
});

const createOpen = ref(false);
const search = ref(props.filters.q);

function apply(changes: Partial<{ q: string; status: string }>) {
    const next = { ...props.filters, ...changes };

    router.get(
        SituationController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

// Exemple du cahier des charges (CA11), calculé avec la même formule que le serveur.
const example = computeSituation({
    marketAmount: 100_000_000,
    progressCumul: 40,
    previousProgress: 30,
    retentionRate: 5,
    advanceRefundRate: 10,
    vatRate: 18,
});

const fields: FieldDef[] = [
    {
        name: 'contract_id',
        label: 'Marché',
        type: 'select',
        required: true,
        wide: true,
        options: props.contracts.map((c) => ({
            value: String(c.id),
            label: c.label,
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
        hint: 'Il ne peut pas être inférieur à celui de la situation précédente.',
    },
    {
        name: 'situation_date',
        label: 'Date de la situation',
        type: 'date',
        required: true,
    },
];

function create(v: FieldValues) {
    router.post(
        SituationController.store.url(),
        {
            contract_id: Number(v.contract_id),
            progress: Number(v.progress),
            situation_date: String(v.situation_date),
        },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Saisie invalide.'),
        },
    );
}

const columns: DataTableColumn<SituationRow>[] = [
    {
        accessorKey: 'number',
        header: 'Situation',
        cell: ({ row }) =>
            linkCell(
                SituationController.show.url(row.original.id),
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
        cell: ({ row }) => badgeCell('situation', row.original.status),
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
            <Button v-if="can.create" @click="createOpen = true"
                ><Plus /> Nouvelle situation</Button
            >
        </PageHeader>

        <div class="flex flex-wrap gap-3">
            <form
                class="flex-1 sm:max-w-xs"
                @submit.prevent="apply({ q: search })"
            >
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher une situation…"
                    aria-label="Rechercher une situation"
                />
            </form>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par statut"
                :value="filters.status"
                @change="
                    apply({
                        status: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Statut : tous</option>
                <option
                    v-for="(meta, value) in statuses.situation"
                    :key="value"
                    :value="value"
                >
                    {{ meta.label }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="situations.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucune situation."
        />
        <PaginationBar :page="situations" />

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
            :initial="{ situation_date: TODAY }"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
