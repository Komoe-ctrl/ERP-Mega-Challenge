<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { h } from 'vue';
import ActivityController from '@/actions/App/Http/Controllers/Administration/ActivityController';
import DataTable from '@/components/DataTable.vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { badgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateTimeFr } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { ActivityEntry } from '@/types/erp';

const props = defineProps<{
    entries: Paginated<ActivityEntry>;
    filters: { event: string; actor: string };
    actors: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Journal des activités', href: '/administration/journal' },
        ],
    },
});

const columns: DataTableColumn<ActivityEntry>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateTimeFr(row.original.date.replace(' ', 'T')),
    },
    { accessorKey: 'actor', header: 'Auteur' },
    {
        accessorKey: 'event',
        header: 'Événement',
        cell: ({ row }) => badgeCell('activity', row.original.event),
    },
    { accessorKey: 'subject', header: 'Objet' },
    {
        id: 'changes',
        header: 'Valeurs avant → après',
        cell: ({ row }) =>
            h(
                'ul',
                { class: 'space-y-0.5 text-xs' },
                row.original.changes.map((c) =>
                    h('li', [
                        h(
                            'span',
                            { class: 'text-muted-foreground' },
                            `${c.field} : `,
                        ),
                        h(
                            'span',
                            { class: 'line-through opacity-70' },
                            c.before,
                        ),
                        ' → ',
                        h('span', { class: 'font-medium' }, c.after),
                    ]),
                ),
            ),
    },
];

// Filtres appliqués par le serveur : le journal peut compter des milliers de lignes.
function filter(name: 'event' | 'actor', value: string) {
    router.get(
        ActivityController.index.url(),
        { ...props.filters, [name]: value || undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Journal des activités" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Journal des activités"
            description="Toute création, modification, validation ou suppression : auteur, date, valeurs avant et après. Non modifiable."
        />
        <AdminNav />
        <div class="flex flex-wrap gap-3">
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par événement"
                :value="filters.event"
                @change="
                    filter('event', ($event.target as HTMLSelectElement).value)
                "
            >
                <option value="">Tous les événements</option>
                <option
                    v-for="(meta, value) in statuses.activity"
                    :key="value"
                    :value="value"
                >
                    {{ meta.label }}
                </option>
            </select>
            <select
                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                aria-label="Filtrer par auteur"
                :value="filters.actor"
                @change="
                    filter('actor', ($event.target as HTMLSelectElement).value)
                "
            >
                <option value="">Tous les auteurs</option>
                <option v-for="a in actors" :key="a.value" :value="a.value">
                    {{ a.label }}
                </option>
            </select>
        </div>
        <DataTable
            :columns="columns"
            :data="entries.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucune activité enregistrée."
        />
        <PaginationBar :page="entries" />
    </div>
</template>
