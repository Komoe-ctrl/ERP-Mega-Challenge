<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, ClipboardCheck, Plus } from '@lucide/vue';
import { ref } from 'vue';
import PurchaseRequestController from '@/actions/App/Http/Controllers/Achats/PurchaseRequestController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { PurchaseRequest } from '@/types/erp';

type Row = Omit<PurchaseRequest, 'lines' | 'history'>;
type Filters = { q: string; status: string; chantier: string };

const props = defineProps<{
    requests: Paginated<Row>;
    filters: Filters;
    chantiers: { value: string; label: string }[];
    pending: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achats', href: '/achats/demandes' }] },
});

const { can } = usePermissions();
const search = ref(props.filters.q);

function apply(changes: Partial<Filters>) {
    const next = { ...props.filters, ...changes };

    router.get(
        PurchaseRequestController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Demande',
        cell: ({ row }) =>
            linkCell(
                PurchaseRequestController.show.url(row.original.id),
                row.original.number,
                row.original.justification,
            ),
    },
    { accessorKey: 'chantierName', header: 'Chantier' },
    { accessorKey: 'requester', header: 'Demandeur' },
    {
        accessorKey: 'createdAt',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.createdAt),
    },
    {
        accessorKey: 'total',
        header: 'Montant',
        cell: ({ row }) => amountCell(fcfa(row.original.total ?? 0)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('request', row.original.status),
    },
];
</script>

<template>
    <Head title="Demandes d’achat" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Demandes d’achat"
            description="De la saisie sur chantier à la validation, aux prix du référentiel."
        >
            <Button variant="outline" as-child>
                <Link href="/achats/referentiel"
                    ><BookOpen /> Référentiel des prix</Link
                >
            </Button>
            <Button v-if="can('achats.validate')" variant="outline" as-child>
                <Link :href="PurchaseRequestController.pending.url()"
                    ><ClipboardCheck /> À valider ({{ pending }})</Link
                >
            </Button>
            <Button v-if="can('achats.create')" as-child>
                <Link :href="PurchaseRequestController.create.url()"
                    ><Plus /> Nouvelle demande</Link
                >
            </Button>
        </PageHeader>

        <div class="flex flex-wrap gap-3">
            <form
                class="flex-1 sm:max-w-xs"
                @submit.prevent="apply({ q: search })"
            >
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Rechercher une demande…"
                    aria-label="Rechercher une demande"
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
                    v-for="(meta, value) in statuses.request"
                    :key="value"
                    :value="value"
                >
                    {{ meta.label }}
                </option>
            </select>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par chantier"
                :value="filters.chantier"
                @change="
                    apply({
                        chantier: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Chantier : tous</option>
                <option v-for="c in chantiers" :key="c.value" :value="c.value">
                    {{ c.label }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="requests.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucune demande ne correspond à ces filtres."
        />
        <PaginationBar :page="requests" />
    </div>
</template>
