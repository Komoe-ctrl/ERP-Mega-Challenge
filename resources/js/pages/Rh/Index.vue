<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { h } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { categoryLabels, employees } from '@/mocks/v4';
import type { Employee } from '@/types/erp';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ressources humaines', href: '/rh' }] },
});

const { can } = usePermissions();

const columns: DataTableColumn<Employee>[] = [
    {
        accessorKey: 'name',
        header: 'Employé',
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-medium' }, row.original.name),
                h(
                    'div',
                    { class: 'text-xs text-muted-foreground' },
                    `${row.original.matricule} · ${row.original.job}`,
                ),
            ]),
    },
    {
        accessorKey: 'category',
        header: 'Catégorie',
        filterFn: 'equalsString',
        cell: ({ row }) => categoryLabels[row.original.category],
    },
    { accessorKey: 'contract', header: 'Contrat', filterFn: 'equalsString' },
    { accessorKey: 'chantier', header: 'Affectation' },
    {
        accessorKey: 'hourlyCost',
        header: 'Coût horaire',
        cell: ({ row }) => amountCell(fcfa(row.original.hourlyCost)),
    },
    {
        accessorKey: 'baseSalary',
        header: 'Salaire de base',
        cell: ({ row }) =>
            amountCell(
                row.original.baseSalary ? fcfa(row.original.baseSalary) : '—',
            ),
    },
    {
        accessorKey: 'onLeave',
        header: 'Situation',
        cell: ({ row }) =>
            h(
                StatusBadge,
                row.original.onLeave
                    ? { label: 'En congé', tone: 'warning' }
                    : { label: 'En poste', tone: 'success' },
            ),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'category',
        label: 'Catégorie',
        options: Object.entries(categoryLabels).map(([value, label]) => ({
            value,
            label,
        })),
    },
    {
        columnId: 'contract',
        label: 'Contrat',
        options: ['CDI', 'CDD', 'Journalier'].map((c) => ({
            value: c,
            label: c,
        })),
    },
];
</script>

<template>
    <Head title="Ressources humaines" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Ressources humaines"
            description="Référence du personnel et de la paie ; la Main-d’œuvre en est la vue chantier."
        >
            <Button
                v-if="can('rh.create')"
                @click="toast.success('Création d’un employé (maquette).')"
                ><Plus /> Nouvel employé</Button
            >
        </PageHeader>
        <SubNav :links="rhNav" />
        <DataTable
            :columns="columns"
            :data="employees"
            :filters="filters"
            search-placeholder="Rechercher un employé…"
        />
    </div>
</template>
