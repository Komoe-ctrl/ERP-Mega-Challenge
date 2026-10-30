<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { chantiers } from '@/mocks/chantiers';
import { categoryLabels, employees } from '@/mocks/v4';
import type { Employee } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ressources humaines', href: '/rh' }] },
});

const { can } = usePermissions();
const rows = ref<Employee[]>(employees.map((e) => ({ ...e })));
const createOpen = ref(false);

// RG13.1 : la fiche porte le contrat (type, dates, salaire de base, coût horaire).
const fields: FieldDef[] = [
    {
        name: 'name',
        label: 'Nom complet',
        required: true,
        minLength: 3,
        wide: true,
    },
    { name: 'job', label: 'Fonction', required: true },
    {
        name: 'category',
        label: 'Catégorie',
        type: 'select',
        required: true,
        options: Object.entries(categoryLabels).map(([value, label]) => ({
            value,
            label,
        })),
    },
    {
        name: 'contract',
        label: 'Type de contrat',
        type: 'select',
        required: true,
        options: ['CDI', 'CDD', 'Journalier'].map((c) => ({
            value: c,
            label: c,
        })),
    },
    {
        name: 'startDate',
        label: 'Début du contrat',
        type: 'date',
        required: true,
    },
    {
        name: 'baseSalary',
        label: 'Salaire de base mensuel',
        type: 'number',
        required: true,
        min: 0,
        step: 1,
        unit: 'FCFA',
        hint: '0 pour un journalier.',
    },
    {
        name: 'hourlyCost',
        label: 'Coût horaire (charges comprises)',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
    },
    {
        name: 'chantier',
        label: 'Affectation',
        type: 'select',
        required: true,
        options: [
            { value: 'Siège', label: 'Siège' },
            ...chantiers
                .filter((c) => c.status === 'en_cours')
                .map((c) => ({ value: c.name, label: c.name })),
        ],
    },
];

function create(v: FieldValues) {
    rows.value.push({
        id: Date.now(),
        matricule: `EMP-${String(70 + rows.value.length).padStart(3, '0')}`,
        name: String(v.name),
        category: v.category as Employee['category'],
        job: String(v.job),
        contract: v.contract as Employee['contract'],
        hourlyCost: Number(v.hourlyCost),
        baseSalary: Number(v.baseSalary),
        chantier: String(v.chantier),
        onLeave: false,
    });
    toast.success('Employé créé (maquette).');
}

const columns: DataTableColumn<Employee>[] = [
    {
        accessorKey: 'name',
        header: 'Employé',
        cell: ({ row }) =>
            linkCell(
                `/rh/${row.original.id}`,
                row.original.name,
                `${row.original.matricule} · ${row.original.job}`,
            ),
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
            <Button v-if="can('rh.create')" @click="createOpen = true"
                ><Plus /> Nouvel employé</Button
            >
        </PageHeader>
        <SubNav :links="rhNav" />
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un employé…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouvel employé"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
