<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ClipboardCheck, Plus } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { achatsNav } from '@/lib/navs';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import { requestTotal, requests } from '@/mocks/achats';
import { chantiers } from '@/mocks/chantiers';
import type { PurchaseRequest } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Achats', href: '/achats/demandes' }] },
});

const { can } = usePermissions();

type Row = PurchaseRequest & { total: number };
const rows: Row[] = requests.map((r) => ({ ...r, total: requestTotal(r) }));
const pending = requests.filter((r) => r.status === 'en_attente').length;

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'number',
        header: 'Demande',
        cell: ({ row }) =>
            linkCell(
                `/achats/demandes/${row.original.id}`,
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
        cell: ({ row }) => amountCell(fcfa(row.original.total)),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('request', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.request).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
    {
        columnId: 'chantierName',
        label: 'Chantier',
        options: chantiers.map((c) => ({ value: c.name, label: c.name })),
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
                <Link href="/achats/a-valider"
                    ><ClipboardCheck /> À valider ({{ pending }})</Link
                >
            </Button>
            <Button v-if="can('achats.create')" as-child>
                <Link href="/achats/demandes/creer"
                    ><Plus /> Nouvelle demande</Link
                >
            </Button>
        </PageHeader>

        <SubNav :links="achatsNav" />

        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher une demande…"
        />
    </div>
</template>
