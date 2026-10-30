<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { badgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr, TODAY } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { employees, leaves } from '@/mocks/v4';
import type { Leave } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ressources humaines', href: '/rh' },
            { title: 'Congés', href: '/rh/conges' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<Leave[]>(leaves.map((l) => ({ ...l })));
const createOpen = ref(false);
const refusing = ref<number | null>(null);
const refuseOpen = computed({
    get: () => refusing.value !== null,
    set: (v) => {
        if (!v) refusing.value = null;
    },
});

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
        name: 'type',
        label: 'Type de congé',
        type: 'select',
        required: true,
        options: ['Congé annuel', 'Congé maladie', 'Congé exceptionnel'].map(
            (t) => ({ value: t, label: t }),
        ),
    },
    {
        name: 'from',
        label: 'Du',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) < TODAY
                ? 'La date de début ne peut pas être dans le passé.'
                : null,
    },
    {
        name: 'to',
        label: 'Au',
        type: 'date',
        required: true,
        validate: (v, values) => {
            if (values.from && v && String(v) < String(values.from)) {
                return 'La fin doit être postérieure au début.';
            }

            const clash = rows.value.find(
                (l) =>
                    l.employee === values.employee &&
                    l.status !== 'refusee' &&
                    String(values.from) <= l.to &&
                    String(v) >= l.from,
            );

            return clash
                ? `Chevauchement avec un congé du ${dateFr(clash.from)} au ${dateFr(clash.to)}.`
                : null;
        },
    },
]);

function create(v: FieldValues) {
    rows.value.unshift({
        id: Date.now(),
        employee: String(v.employee),
        type: String(v.type),
        from: String(v.from),
        to: String(v.to),
        status: 'demandee',
    });
    toast.success(
        'Demande de congé enregistrée : elle suit le circuit de validation (maquette).',
    );
}

function approve(l: Leave) {
    l.status = 'validee';
    toast.success(
        `Congé de ${l.employee} validé : l’employé ne pourra plus être pointé sur cette période.`,
    );
}

function refuse(reason: string) {
    const l = rows.value.find((x) => x.id === refusing.value);

    if (l) {
        l.status = 'refusee';
        toast.success(`Congé refusé : « ${reason} ».`);
    }
}

const columns: DataTableColumn<Leave>[] = [
    { accessorKey: 'employee', header: 'Employé' },
    { accessorKey: 'type', header: 'Type' },
    {
        accessorKey: 'from',
        header: 'Du',
        cell: ({ row }) => dateFr(row.original.from),
    },
    {
        accessorKey: 'to',
        header: 'Au',
        cell: ({ row }) => dateFr(row.original.to),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        cell: ({ row }) => badgeCell('leave', row.original.status),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) =>
            row.original.status === 'demandee' && can('rh.validate')
                ? h('div', { class: 'flex justify-end gap-2' }, [
                      h(
                          Button,
                          { size: 'sm', onClick: () => approve(row.original) },
                          () => 'Valider',
                      ),
                      h(
                          Button,
                          {
                              size: 'sm',
                              variant: 'outline',
                              onClick: () => (refusing.value = row.original.id),
                          },
                          () => 'Refuser',
                      ),
                  ])
                : null,
    },
];
</script>

<template>
    <Head title="Congés" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Congés"
            description="Circuit de demande et de validation ; un employé en congé ne peut pas être pointé (RG13.2)."
        >
            <Button v-if="can('rh.create')" @click="createOpen = true"
                ><Plus /> Demander un congé</Button
            >
        </PageHeader>
        <SubNav :links="rhNav" />
        <DataTable :columns="columns" :data="rows" :searchable="false" />
        <FormDialog
            v-model:open="createOpen"
            title="Demande de congé"
            :fields="fields"
            @submit="create"
        />
        <ConfirmDialog
            v-model:open="refuseOpen"
            title="Refuser la demande de congé"
            confirm-label="Refuser"
            require-reason
            destructive
            @confirm="refuse"
        />
    </div>
</template>
