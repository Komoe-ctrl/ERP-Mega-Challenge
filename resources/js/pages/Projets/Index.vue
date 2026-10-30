<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projets/ProjectController';
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
import type { Project } from '@/types/erp';

type Option = { value: string; label: string };
type Filters = {
    q: string;
    status: string;
    client: string;
    manager: string;
    agency: string;
};

const props = defineProps<{
    projects: Paginated<Project>;
    filters: Filters;
    options: { clients: Option[]; managers: Option[]; agencies: Option[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projets', href: '/projets' }] },
});

const { can } = usePermissions();
const search = ref(props.filters.q);

// CA4 : filtres par statut, client, responsable et agence, appliqués par le serveur.
function apply(changes: Partial<Filters>) {
    const next = { ...props.filters, ...changes };

    router.get(
        ProjectController.index.url(),
        Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')),
        { preserveState: true, replace: true },
    );
}

const selects: { name: keyof Filters; label: string; options: Option[] }[] = [
    {
        name: 'status',
        label: 'Statut',
        options: Object.entries(statuses.project).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
    { name: 'client', label: 'Client', options: props.options.clients },
    { name: 'manager', label: 'Responsable', options: props.options.managers },
    { name: 'agency', label: 'Agence', options: props.options.agencies },
];

const columns: DataTableColumn<Project>[] = [
    {
        accessorKey: 'name',
        header: 'Projet',
        cell: ({ row }) =>
            linkCell(
                ProjectController.show.url(row.original.id),
                row.original.name,
                row.original.number,
            ),
    },
    { accessorKey: 'clientName', header: 'Client' },
    { accessorKey: 'manager', header: 'Responsable' },
    { accessorKey: 'agency', header: 'Agence' },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('project', row.original.status),
    },
    {
        accessorKey: 'budget',
        header: 'Budget',
        cell: ({ row }) => amountCell(fcfa(row.original.budget)),
    },
    {
        accessorKey: 'endDate',
        header: 'Fin prévue',
        cell: ({ row }) => dateFr(row.original.endDate),
    },
];
</script>

<template>
    <Head title="Projets" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Projets"
            description="Du premier contact au dossier clôturé."
        >
            <Button v-if="can('projets.create')" as-child>
                <Link :href="ProjectController.create.url()"
                    ><Plus /> Nouveau projet</Link
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
                    placeholder="Rechercher un projet, un client…"
                    aria-label="Rechercher un projet"
                />
            </form>
            <select
                v-for="s in selects"
                :key="s.name"
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                :aria-label="`Filtrer par ${s.label.toLowerCase()}`"
                :value="filters[s.name]"
                @change="
                    apply({
                        [s.name]: ($event.target as HTMLSelectElement).value,
                    })
                "
            >
                <option value="">{{ s.label }} : tous</option>
                <option v-for="o in s.options" :key="o.value" :value="o.value">
                    {{ o.label }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="projects.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucun projet ne correspond à ces filtres."
        />
        <PaginationBar :page="projects" />
    </div>
</template>
