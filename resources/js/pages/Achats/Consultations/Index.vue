<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn, DataTableFilter } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import { requestTotal, requests } from '@/mocks/achats';
import { consultations } from '@/mocks/v2';
import type { Consultation } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Consultations', href: '/achats/consultations' },
        ],
    },
});

const { can } = usePermissions();
const rows = ref<Consultation[]>(consultations.map((c) => ({ ...c })));
const createOpen = ref(false);

// Une consultation porte sur une demande validée qui n'a pas encore de consultation.
const consultable = computed(() =>
    requests.filter(
        (r) =>
            r.status === 'validee' &&
            !rows.value.some((c) => c.requestNumber === r.number),
    ),
);
const fields = computed<FieldDef[]>(() => [
    {
        name: 'request',
        label: 'Demande validée',
        type: 'select',
        required: true,
        options: consultable.value.map((r) => ({
            value: r.number,
            label: `${r.number} — ${r.chantierName} (${fcfa(requestTotal(r))})`,
        })),
        wide: true,
        hint: consultable.value.length
            ? 'Au-delà du seuil paramétré, au moins 3 fournisseurs sont consultés (RG8.11).'
            : 'Aucune demande validée à consulter.',
    },
    {
        name: 'deadline',
        label: 'Date limite de réponse',
        type: 'date',
        required: true,
    },
    { name: 'note', label: 'Précisions aux fournisseurs', type: 'textarea' },
]);

function create(v: FieldValues) {
    const request = requests.find((r) => r.number === v.request);

    if (!request) {
        return;
    }

    rows.value.unshift({
        id: Date.now(),
        number: `CS-2026-00${33 + rows.value.length - consultations.length}`,
        requestNumber: request.number,
        chantierName: request.chantierName,
        status: 'ouverte',
        items: request.justification,
        minSuppliers: 3,
        quotes: [],
    });
    toast.success(
        'Consultation ouverte : les fournisseurs de la catégorie concernée sont sollicités (maquette).',
    );
}

const columns: DataTableColumn<Consultation>[] = [
    {
        accessorKey: 'number',
        header: 'Consultation',
        cell: ({ row }) =>
            linkCell(
                `/achats/consultations/${row.original.id}`,
                row.original.number,
                row.original.items,
            ),
    },
    { accessorKey: 'requestNumber', header: 'Demande' },
    { accessorKey: 'chantierName', header: 'Chantier' },
    {
        id: 'offers',
        header: 'Offres reçues',
        accessorFn: (c) => c.quotes.length,
        cell: ({ row }) =>
            `${row.original.quotes.length} / ${row.original.minSuppliers} minimum`,
    },
    {
        id: 'best',
        header: 'Meilleure offre',
        accessorFn: (c) =>
            c.quotes.length ? Math.min(...c.quotes.map((q) => q.unitTotal)) : 0,
        cell: ({ row }) =>
            amountCell(
                row.original.quotes.length
                    ? fcfa(
                          Math.min(
                              ...row.original.quotes.map((q) => q.unitTotal),
                          ),
                      )
                    : '—',
            ),
    },
    {
        accessorKey: 'status',
        header: 'Statut',
        filterFn: 'equalsString',
        cell: ({ row }) => badgeCell('consultation', row.original.status),
    },
];

const filters: DataTableFilter[] = [
    {
        columnId: 'status',
        label: 'Statut',
        options: Object.entries(statuses.consultation).map(([value, meta]) => ({
            value,
            label: meta.label,
        })),
    },
];
</script>

<template>
    <Head title="Cotations" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Cotations des fournisseurs"
            description="Au-delà du seuil paramétré, au moins trois fournisseurs sont consultés avant commande (RG8.11)."
        >
            <Button v-if="can('achats.create')" @click="createOpen = true"
                ><Plus /> Nouvelle consultation</Button
            >
        </PageHeader>
        <DataTable
            :columns="columns"
            :data="rows"
            :filters="filters"
            search-placeholder="Rechercher une consultation…"
        />
        <FormDialog
            v-model:open="createOpen"
            title="Nouvelle consultation"
            :fields="fields"
            submit-label="Ouvrir la consultation"
            @submit="create"
        />
    </div>
</template>
