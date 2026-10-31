<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsultationController from '@/actions/App/Http/Controllers/Achats/ConsultationController';
import DataTable from '@/components/DataTable.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { amountCell, badgeCell, linkCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { fcfa } from '@/lib/format';
import { statuses } from '@/lib/status';
import type { Paginated } from '@/types';
import type { Consultation } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

const props = defineProps<{
    consultations: Paginated<Consultation>;
    filters: { status: string };
    consultable: {
        id: number;
        number: string;
        chantierName: string;
        total: number;
    }[];
    threshold: number;
    minSuppliers: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Achats', href: '/achats/demandes' },
            { title: 'Cotations', href: '/achats/consultations' },
        ],
    },
});

const { can } = usePermissions();
const createOpen = ref(false);

// Une consultation porte sur une demande validée qui n'a pas encore de consultation.
const fields = computed<FieldDef[]>(() => [
    {
        name: 'purchase_request_id',
        label: 'Demande validée',
        type: 'select',
        required: true,
        options: props.consultable.map((r) => ({
            value: String(r.id),
            label: `${r.number} — ${r.chantierName} (${fcfa(r.total)})`,
        })),
        wide: true,
        hint: props.consultable.length
            ? `Au-delà de ${fcfa(props.threshold)}, au moins ${props.minSuppliers} fournisseurs sont consultés (RG8.11).`
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
    router.post(
        ConsultationController.store.url(),
        {
            purchase_request_id: v.purchase_request_id,
            deadline: v.deadline,
            note: v.note,
        },
        {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Saisie invalide.'),
        },
    );
}

function applyStatus(status: string) {
    router.get(ConsultationController.index.url(), status ? { status } : {}, {
        preserveState: true,
        replace: true,
    });
}

const columns: DataTableColumn<Consultation>[] = [
    {
        accessorKey: 'number',
        header: 'Consultation',
        cell: ({ row }) =>
            linkCell(
                ConsultationController.show.url(row.original.id),
                row.original.number,
                row.original.items,
            ),
    },
    { accessorKey: 'requestNumber', header: 'Demande' },
    { accessorKey: 'chantierName', header: 'Chantier' },
    {
        id: 'offers',
        header: 'Offres reçues',
        cell: ({ row }) =>
            `${row.original.quotes.length} / ${row.original.minSuppliers} minimum`,
    },
    {
        id: 'best',
        header: 'Meilleure offre',
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
        cell: ({ row }) => badgeCell('consultation', row.original.status),
    },
];
</script>

<template>
    <Head title="Cotations" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Cotations des fournisseurs"
            :description="`Au-delà de ${fcfa(threshold)}, au moins ${minSuppliers} fournisseurs sont consultés avant commande (RG8.11).`"
        >
            <Button v-if="can('achats.create')" @click="createOpen = true"
                ><Plus /> Nouvelle consultation</Button
            >
        </PageHeader>

        <select
            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
            aria-label="Filtrer par statut"
            :value="filters.status"
            @change="applyStatus(($event.target as HTMLSelectElement).value)"
        >
            <option value="">Statut : tous</option>
            <option
                v-for="(meta, value) in statuses.consultation"
                :key="value"
                :value="value"
            >
                {{ meta.label }}
            </option>
        </select>

        <DataTable
            :columns="columns"
            :data="consultations.data"
            :searchable="false"
            :paginate="false"
            empty-message="Aucune consultation."
        />
        <PaginationBar :page="consultations" />

        <FormDialog
            v-model:open="createOpen"
            title="Nouvelle consultation"
            :fields="fields"
            submit-label="Ouvrir la consultation"
            @submit="create"
        />
    </div>
</template>
