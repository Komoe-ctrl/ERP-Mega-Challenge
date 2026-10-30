<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { dateFr, daysBetween, fcfa, number, TODAY } from '@/lib/format';
import { statuses } from '@/lib/status';
import { equipments } from '@/mocks/v4';
import type { Equipment } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Engins et équipements', href: '/engins' }],
    },
});

const { can } = usePermissions();
const rows = ref<Equipment[]>(equipments.map((e) => ({ ...e })));
const createOpen = ref(false);

// RG14.3 : une alerte part avant l'échéance de maintenance (ici 14 jours).
const dueSoon = (e: Equipment) => daysBetween(TODAY, e.nextMaintenance) <= 14;

const types = [
    'Excavatrice',
    'Grue',
    'Bétonnière',
    'Groupe électrogène',
    'Véhicule',
    'Outillage',
];
const fields: FieldDef[] = [
    {
        name: 'code',
        label: 'Code',
        required: true,
        pattern: /^[A-Z]{2}-\d{2}$/,
        patternMessage:
            'Format attendu : deux lettres, un tiret, deux chiffres (ex. PH-03).',
        validate: (v) =>
            rows.value.some((e) => e.code === v)
                ? 'Ce code existe déjà.'
                : null,
    },
    { name: 'name', label: 'Désignation', required: true, minLength: 3 },
    {
        name: 'type',
        label: 'Type',
        type: 'select',
        required: true,
        options: types.map((t) => ({ value: t, label: t })),
    },
    {
        name: 'hourlyCost',
        label: 'Coût horaire',
        type: 'number',
        required: true,
        min: 1,
        step: 1,
        unit: 'FCFA',
    },
    {
        name: 'nextMaintenance',
        label: 'Première maintenance préventive',
        type: 'date',
        required: true,
        validate: (v) =>
            v && String(v) < TODAY ? 'La date doit être dans le futur.' : null,
    },
];

function create(v: FieldValues) {
    rows.value.push({
        id: Date.now(),
        code: String(v.code),
        name: String(v.name),
        type: String(v.type),
        chantier: '—',
        status: 'disponible',
        hours: 0,
        fuelLiters: 0,
        repairCost: 0,
        nextMaintenance: String(v.nextMaintenance),
    });
    toast.success('Engin ajouté au parc (maquette).');
}

const columns: DataTableColumn<Equipment>[] = [
    {
        accessorKey: 'name',
        header: 'Engin',
        cell: ({ row }) =>
            linkCell(
                `/engins/${row.original.id}`,
                row.original.name,
                row.original.code,
            ),
    },
    { accessorKey: 'type', header: 'Type', filterFn: 'equalsString' },
    { accessorKey: 'chantier', header: 'Chantier actuel' },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('equipment', row.original.status),
    },
    {
        accessorKey: 'hours',
        header: 'Heures',
        cell: ({ row }) => amountCell(number(row.original.hours)),
    },
    {
        accessorKey: 'nextMaintenance',
        header: 'Prochaine maintenance',
        cell: ({ row }) =>
            h('div', { class: 'flex items-center gap-2' }, [
                dateFr(row.original.nextMaintenance),
                dueSoon(row.original)
                    ? h(StatusBadge, {
                          label: 'Échéance proche',
                          tone: 'warning',
                      })
                    : null,
            ]),
    },
    {
        accessorKey: 'repairCost',
        header: 'Réparations',
        cell: ({ row }) => amountCell(fcfa(row.original.repairCost)),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.equipment).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
    {
        columnId: 'type',
        label: 'Type',
        options: types.map((t) => ({ value: t, label: t })),
    },
];
</script>

<template>
    <Head title="Engins et équipements" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Engins et équipements"
            description="Un engin est affecté à un seul chantier à la fois ; pendant une réparation, il est indisponible (RG14.1, RG14.4)."
        >
            <Button v-if="can('engins.create')" @click="createOpen = true"
                ><Plus /> Ajouter un engin</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher un engin…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouvel engin"
            :fields="fields"
            @submit="create"
        />
    </div>
</template>
