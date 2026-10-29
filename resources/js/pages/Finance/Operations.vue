<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { financeNav } from '@/lib/navs';
import { chantiers } from '@/mocks/chantiers';
import { bankBalance, cashBalance, cashEntries } from '@/mocks/v4';
import type { CashEntry } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{ account: 'caisse' | 'banque' }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Finance', href: '/finance' }] },
});

const { can } = usePermissions();
const isCash = computed(() => props.account === 'caisse');
const added = ref<CashEntry[]>([]);
const entries = computed(() =>
    [...added.value, ...cashEntries].filter((e) => e.account === props.account),
);
const balance = computed(
    () =>
        (isCash.value ? cashBalance : bankBalance) +
        added.value
            .filter((e) => e.account === props.account)
            .reduce(
                (s, e) => s + (e.kind === 'recette' ? e.amount : -e.amount),
                0,
            ),
);
const createOpen = ref(false);

// RG15.1 : toute opération rattachée à l'exercice courant et, si elle concerne un chantier, à ce chantier.
// RG15.4 : justificatif obligatoire en caisse ; le solde de caisse ne peut pas devenir négatif.
const fields = computed<FieldDef[]>(() => [
    {
        name: 'label',
        label: 'Libellé',
        required: true,
        minLength: 3,
        wide: true,
    },
    {
        name: 'kind',
        label: 'Sens',
        type: 'select',
        required: true,
        options: [
            { value: 'recette', label: 'Recette' },
            { value: 'depense', label: 'Dépense' },
        ],
    },
    {
        name: 'amount',
        label: 'Montant',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
        validate: (v, values) =>
            isCash.value &&
            values.kind === 'depense' &&
            Number(v) > balance.value
                ? `Solde de caisse insuffisant (${fcfa(balance.value)}) : la caisse ne peut pas devenir négative.`
                : null,
    },
    {
        name: 'date',
        label: 'Date',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) > TODAY
                ? 'La date ne peut pas être dans le futur.'
                : null,
    },
    {
        name: 'chantier',
        label: 'Chantier concerné',
        type: 'select',
        options: chantiers
            .filter((c) => c.status !== 'cloture')
            .map((c) => ({ value: c.name, label: c.name })),
    },
    {
        name: 'receipt',
        label: 'Justificatif',
        type: 'file',
        required: isCash.value,
        hint: isCash.value
            ? 'Obligatoire pour toute opération de caisse.'
            : 'Référence de virement ou pièce jointe.',
    },
]);

function create(v: FieldValues) {
    added.value.unshift({
        id: Date.now(),
        date: String(v.date),
        label: String(v.label),
        kind: v.kind as CashEntry['kind'],
        amount: Number(v.amount),
        chantier: v.chantier ? String(v.chantier) : undefined,
        receipt: v.receipt ? String(v.receipt) : 'À fournir',
        account: props.account,
    });
    toast.success('Opération enregistrée sur l’exercice 2026 (maquette).');
}

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

// Rapprochement bancaire (RG15.3) : relevé importé comparé aux opérations enregistrées.
const statement = [
    {
        date: '2026-10-26',
        label: 'VIR RECU IMMO IVOIRE',
        amount: 38_900_000,
        kind: 'recette',
    },
    {
        date: '2026-10-24',
        label: 'CHQ 0045812',
        amount: 6_480_000,
        kind: 'depense',
    },
    {
        date: '2026-10-20',
        label: 'VIR PAIE 09/26',
        amount: 21_350_000,
        kind: 'depense',
    },
    {
        date: '2026-10-22',
        label: 'FRAIS DE TENUE DE COMPTE',
        amount: 45_000,
        kind: 'depense',
    },
];
const reconciliation = computed(() =>
    statement.map((s) => ({
        ...s,
        matched: entries.value.some(
            (e) => e.amount === s.amount && e.kind === s.kind,
        ),
    })),
);
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
            <Button v-if="can('finance.create')" @click="createOpen = true"
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

        <section v-if="!isCash" class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-3 font-display font-semibold">
                Rapprochement bancaire
            </h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="s in reconciliation"
                    :key="s.label"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <span>
                        <span class="font-medium">{{ s.label }}</span>
                        <span class="block text-xs text-muted-foreground"
                            >{{ dateFr(s.date) }} ·
                            {{ s.kind === 'recette' ? '+' : '−'
                            }}{{ fcfa(s.amount) }}</span
                        >
                    </span>
                    <StatusBadge
                        :label="
                            s.matched
                                ? 'Rapprochée'
                                : 'Écart : opération non enregistrée'
                        "
                        :tone="s.matched ? 'success' : 'danger'"
                    />
                </li>
            </ul>
        </section>

        <FormDialog
            v-model:open="createOpen"
            :title="isCash ? 'Opération de caisse' : 'Opération bancaire'"
            :initial="{ date: TODAY }"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
