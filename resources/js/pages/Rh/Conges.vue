<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import SubNav from '@/components/erp/SubNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { badgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { dateFr } from '@/lib/format';
import { rhNav } from '@/lib/navs';
import { leaves } from '@/mocks/v4';
import type { Leave } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ressources humaines', href: '/rh' },
            { title: 'Congés', href: '/rh/conges' },
        ],
    },
});

const { can } = usePermissions();
const refuseOpen = ref(false);

function approve(l: Leave) {
    toast.success(`Congé de ${l.employee} validé (maquette).`);
}
function refuse(reason: string) {
    toast.success(`Congé refusé : « ${reason} » (maquette).`);
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
                              onClick: () => (refuseOpen.value = true),
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
        />
        <SubNav :links="rhNav" />
        <DataTable :columns="columns" :data="leaves" :searchable="false" />
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
