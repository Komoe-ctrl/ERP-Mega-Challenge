<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, fcfa, TODAY } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { advances, employees } from '@/mocks/v4';
import type { Advance } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ressources humaines', href: '/rh' },
            { title: 'Avances', href: '/rh/avances' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<Advance[]>(advances.map((a) => ({ ...a })));
const createOpen = ref(false);

const fields = computed<FieldDef[]>(() => [
    {
        name: 'employee',
        label: 'Employé',
        type: 'select',
        required: true,
        wide: true,
        options: employees.map((e) => ({ value: e.name, label: e.name })),
    },
    {
        name: 'amount',
        label: 'Montant de l’avance',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
        hint: 'Déduite automatiquement de la paie suivante (RG13.3). Plafond : le salaire de base.',
        validate: (v, values) => {
            const e = employees.find((x) => x.name === values.employee);

            return e && e.baseSalary > 0 && Number(v) > e.baseSalary
                ? `L’avance ne peut pas dépasser le salaire de base (${fcfa(e.baseSalary)}).`
                : null;
        },
    },
    { name: 'date', label: 'Date', type: 'date', required: true },
]);

function create(v: FieldValues) {
    rows.value.unshift({
        id: Date.now(),
        employee: String(v.employee),
        date: String(v.date),
        amount: Number(v.amount),
        deducted: 0,
    });
    toast.success(
        'Avance enregistrée : elle sera déduite de la prochaine paie (maquette).',
    );
}

const columns: DataTableColumn<Advance>[] = [
    { accessorKey: 'employee', header: 'Employé' },
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }) => dateFr(row.original.date),
    },
    {
        accessorKey: 'amount',
        header: 'Avance',
        cell: ({ row }) => amountCell(fcfa(row.original.amount)),
    },
    {
        accessorKey: 'deducted',
        header: 'Déjà déduit',
        cell: ({ row }) => amountCell(fcfa(row.original.deducted)),
    },
    {
        id: 'left',
        header: 'Reste à déduire',
        accessorFn: (a) => a.amount - a.deducted,
        cell: ({ row }) =>
            amountCell(fcfa(row.original.amount - row.original.deducted)),
    },
];
</script>

<template>
    <Head title="Avances sur salaire" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Avances sur salaire"
            description="Les avances sont déduites automatiquement de la paie suivante (RG13.3)."
        >
            <Button v-if="can('rh.create')" @click="createOpen = true"
                ><Plus /> Enregistrer une avance</Button
            >
        </PageHeader>
        <SubNav :links="rhNav" />
        <DataTable :columns="columns" :data="rows" :searchable="false" />
        <FormDialog
            v-model:open="createOpen"
            title="Nouvelle avance sur salaire"
            :initial="{ date: TODAY }"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
