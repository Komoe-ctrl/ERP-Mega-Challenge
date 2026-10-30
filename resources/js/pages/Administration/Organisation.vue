<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import OrganizationController from '@/actions/App/Http/Controllers/Administration/OrganizationController';
import DataTable from '@/components/DataTable.vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import ConfirmDialog from '@/components/erp/ConfirmDialog.vue';
import FormDialog from '@/components/erp/FormDialog.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import type { Agency, Service, SiteRecord } from '@/types/erp';
import type { FieldDef, FieldValues } from '@/types/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Organisation', href: '/administration/organisation' },
        ],
    },
});

const { can } = usePermissions();
const canEdit = computed(() => can('administration.update'));
const tab = ref('agences');
const createOpen = ref(false);

type SiteRow = SiteRecord & { agencyId: number };

const props = defineProps<{
    agencies: Agency[];
    services: Service[];
    sites: SiteRow[];
}>();

// Les listes viennent du serveur ; chaque écriture recharge la page.
const agencyRows = computed(() => props.agencies);
const serviceRows = computed(() => props.services);
const siteRows = computed(() => props.sites);

const siteTypes = {
    bureau: 'Bureau',
    depot_central: 'Dépôt central',
    magasin_chantier: 'Magasin de chantier',
} as const;
const uniqueCode = (codes: string[]) => (value: unknown) =>
    codes.includes(String(value).toUpperCase()) ? 'Ce code existe déjà.' : null;

const fieldsByTab = computed<Record<string, FieldDef[]>>(() => ({
    agences: [
        {
            name: 'code',
            label: 'Code',
            required: true,
            pattern: /^[A-Za-z]{2,6}$/,
            patternMessage: '2 à 6 lettres.',
            validate: uniqueCode(agencyRows.value.map((a) => a.code)),
        },
        {
            name: 'name',
            label: 'Nom de l’agence',
            required: true,
            minLength: 3,
        },
        { name: 'city', label: 'Ville', required: true },
    ],
    services: [
        {
            name: 'code',
            label: 'Code',
            required: true,
            pattern: /^[A-Za-z]{2,6}$/,
            patternMessage: '2 à 6 lettres.',
            validate: uniqueCode(serviceRows.value.map((s) => s.code)),
        },
        { name: 'name', label: 'Nom du service', required: true, minLength: 3 },
    ],
    sites: [
        {
            name: 'code',
            label: 'Code',
            required: true,
            pattern: /^[A-Za-z0-9-]{3,12}$/,
            patternMessage: '3 à 12 caractères (lettres, chiffres, tiret).',
            validate: uniqueCode(siteRows.value.map((s) => s.code)),
        },
        { name: 'name', label: 'Nom du site', required: true, minLength: 3 },
        {
            name: 'agency',
            label: 'Agence',
            type: 'select',
            required: true,
            options: agencyRows.value
                .filter((a) => a.active)
                .map((a) => ({
                    value: String(a.id),
                    label: a.name,
                })),
        },
        {
            name: 'type',
            label: 'Type',
            type: 'select',
            required: true,
            options: Object.entries(siteTypes).map(([value, label]) => ({
                value,
                label,
            })),
        },
    ],
}));

type Kind = 'agences' | 'services' | 'sites';

const routes = {
    agences: {
        store: () => OrganizationController.storeAgency.url(),
        deactivate: (id: number) =>
            OrganizationController.deactivateAgency.url(id),
        reactivate: (id: number) =>
            OrganizationController.reactivateAgency.url(id),
    },
    services: {
        store: () => OrganizationController.storeService.url(),
        deactivate: (id: number) =>
            OrganizationController.deactivateService.url(id),
        reactivate: (id: number) =>
            OrganizationController.reactivateService.url(id),
    },
    sites: {
        store: () => OrganizationController.storeSite.url(),
        deactivate: (id: number) =>
            OrganizationController.deactivateSite.url(id),
        reactivate: (id: number) =>
            OrganizationController.reactivateSite.url(id),
    },
} as const;

const labels: Record<string, string> = {
    agences: 'agence',
    services: 'service',
    sites: 'site',
};

// La validation est refaite côté serveur ; les erreurs reviennent par Inertia.
function create(v: FieldValues) {
    const payload: Record<string, unknown> = {
        code: String(v.code).toUpperCase(),
        name: v.name,
    };

    if (tab.value === 'agences') {
        payload.city = v.city;
    } else if (tab.value === 'sites') {
        payload.agency_id = v.agency;
        payload.type = v.type;
    }

    router.post(routes[tab.value as Kind].store(), payload as never, {
        preserveScroll: true,
        onError: (errors) => toast.error(Object.values(errors)[0]),
    });
}

// Désactivation avec motif ; on ne supprime jamais (historique conservé).
const toggling = ref<{ kind: Kind; id: number } | null>(null);
const toggleOpen = computed({
    get: () => toggling.value !== null,
    set: (v) => {
        if (!v) toggling.value = null;
    },
});

function askToggle(kind: Kind, row: { id: number; active: boolean }) {
    if (row.active) {
        toggling.value = { kind, id: row.id };
    } else {
        router.patch(
            routes[kind].reactivate(row.id),
            {},
            { preserveScroll: true },
        );
    }
}

function confirmToggle(reason: string) {
    if (toggling.value) {
        router.patch(
            routes[toggling.value.kind].deactivate(toggling.value.id),
            { reason },
            { preserveScroll: true },
        );
    }
}

const stateCell = (kind: Kind, row: { id: number; active: boolean }) =>
    h('div', { class: 'flex items-center gap-2' }, [
        toneBadgeCell(
            row.active ? 'Actif' : 'Désactivé',
            row.active ? 'success' : 'neutral',
        ),
        canEdit.value
            ? h(
                  Button,
                  {
                      size: 'sm',
                      variant: 'ghost',
                      onClick: () => askToggle(kind, row),
                  },
                  () => (row.active ? 'Désactiver' : 'Réactiver'),
              )
            : null,
    ]);

const agencyCols: DataTableColumn<Agency>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Agence' },
    { accessorKey: 'city', header: 'Ville' },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) => stateCell('agences', row.original),
    },
];
const serviceCols: DataTableColumn<Service>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Service' },
    { accessorKey: 'users', header: 'Utilisateurs' },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) => stateCell('services', row.original),
    },
];
const siteCols: DataTableColumn<SiteRecord>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Site' },
    { accessorKey: 'agency', header: 'Agence' },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => siteTypes[row.original.type],
    },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) => stateCell('sites', row.original),
    },
];
</script>

<template>
    <Head title="Organisation" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Organisation"
            description="Agences, services et sites (dépôts, magasins de chantier). Les données sont filtrées par agence selon les droits."
        >
            <Button v-if="canEdit" @click="createOpen = true"
                ><Plus /> Ajouter
                {{
                    tab === 'agences'
                        ? 'une agence'
                        : tab === 'services'
                          ? 'un service'
                          : 'un site'
                }}</Button
            >
        </PageHeader>
        <AdminNav />
        <TabsNav
            v-model="tab"
            :tabs="[
                { key: 'agences', label: 'Agences', count: agencyRows.length },
                {
                    key: 'services',
                    label: 'Services',
                    count: serviceRows.length,
                },
                { key: 'sites', label: 'Sites', count: siteRows.length },
            ]"
        />
        <DataTable
            v-if="tab === 'agences'"
            :columns="agencyCols"
            :data="agencyRows"
            :searchable="false"
            :paginate="false"
        />
        <DataTable
            v-else-if="tab === 'services'"
            :columns="serviceCols"
            :data="serviceRows"
            :paginate="false"
        />
        <DataTable
            v-else
            :columns="siteCols"
            :data="siteRows"
            :paginate="false"
        />

        <FormDialog
            v-model:open="createOpen"
            :title="
                `Nouvelle ${labels[tab]}`
                    .replace('Nouvelle site', 'Nouveau site')
                    .replace('Nouvelle service', 'Nouveau service')
            "
            :fields="fieldsByTab[tab]"
            @submit="create"
        />
        <ConfirmDialog
            v-model:open="toggleOpen"
            title="Désactiver"
            description="L’élément n’est jamais supprimé : son historique est conservé."
            confirm-label="Désactiver"
            require-reason
            destructive
            @confirm="confirmToggle"
        />
    </div>
</template>
