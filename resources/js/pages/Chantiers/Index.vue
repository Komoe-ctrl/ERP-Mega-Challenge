<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { h, ref } from 'vue';
import ChantierController from '@/actions/App/Http/Controllers/Chantiers/ChantierController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import ProgressBar from '@/components/erp/ProgressBar.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { Chantier } from '@/types/erp';

type Row = Chantier & { progress: number; late: number };
type Filters = { q: string; status: string; project: string };

const props = defineProps<{
    chantiers: Paginated<Row>;
    filters: Filters;
    projects: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Chantiers', href: '/chantiers' }] },
});

const { can } = usePermissions();
const search = ref(props.filters.q);

function apply(changes: Partial<Filters>) {
    const next = { ...props.filters, ...changes };

    router.get(
        ChantierController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

const columns: DataTableColumn<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Chantier',
        cell: ({ row }) =>
            linkCell(
                ChantierController.show.url(row.original.id),
                row.original.name,
                row.original.code,
            ),
    },
    { accessorKey: 'projectName', header: 'Projet' },
    { accessorKey: 'foreman', header: 'Chef de chantier' },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('site', row.original.status),
    },
    {
        accessorKey: 'progress',
        header: 'Avancement',
        cell: ({ row }) =>
            h('div', { class: 'w-32' }, [
                h(ProgressBar, { value: row.original.progress, tone: 'info' }),
            ]),
    },
    {
        accessorKey: 'late',
        header: 'Alertes',
        cell: ({ row }) =>
            row.original.late > 0
                ? h(StatusBadge, {
                      label: `${row.original.late} tâche(s)`,
                      tone: 'warning',
                  })
                : h('span', { class: 'text-muted-foreground' }, '—'),
    },
    {
        accessorKey: 'endDate',
        header: 'Fin prévue',
        cell: ({ row }) => dateFr(row.original.endDate),
    },
];
</script>

<template>
    <Head title="Chantiers" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Chantiers"
            description="Un utilisateur de terrain ne voit que les chantiers auxquels il est affecté."
        >
            <Button v-if="can('chantiers.create')" as-child>
                <Link :href="ChantierController.create.url()"
                    ><Plus /> Nouveau chantier</Link
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
                    placeholder="Rechercher un chantier…"
                    aria-label="Rechercher un chantier"
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
                    v-for="(meta, value) in statuses.site"
                    :key="value"
                    :value="value"
                >
                    {{ meta.label }}
                </option>
            </select>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par projet"
                :value="filters.project"
                @change="
                    apply({
                        project: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">Projet : tous</option>
                <option v-for="p in projects" :key="p.value" :value="p.value">
                    {{ p.label }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="chantiers.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun chantier ne correspond à ces filtres."
        />
        <PaginationBar :page="chantiers" />
    </div>
</template>
