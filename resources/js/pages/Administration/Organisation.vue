<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import { agencies, services, sites } from '@/mocks/transverse';
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

// Listes locales : les créations de la session s'ajoutent aux données fictives.
const agencyRows = ref<Agency[]>(agencies.map((a) => ({ ...a })));
const serviceRows = ref<Service[]>(services.map((s) => ({ ...s })));
const siteRows = ref<SiteRecord[]>(sites.map((s) => ({ ...s })));

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
            options: agencyRows.value.map((a) => ({
                value: a.name,
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

const labels: Record<string, string> = {
    agences: 'agence',
    services: 'service',
    sites: 'site',
};

function create(v: FieldValues) {
    const code = String(v.code).toUpperCase();
    const id = Date.now();

    if (tab.value === 'agences') {
        agencyRows.value.push({
            id,
            code,
            name: String(v.name),
            city: String(v.city),
            active: true,
        });
    } else if (tab.value === 'services') {
        serviceRows.value.push({
            id,
            code,
            name: String(v.name),
            users: 0,
            active: true,
        });
    } else {
        siteRows.value.push({
            id,
            code,
            name: String(v.name),
            agency: String(v.agency),
            type: v.type as SiteRecord['type'],
            active: true,
        });
    }

    toast.success(
        `${labels[tab.value][0].toUpperCase()}${labels[tab.value].slice(1)} créé(e) (maquette).`,
    );
}

// Désactivation avec motif ; on ne supprime jamais (historique conservé).
const toggling = ref<{ rows: { active: boolean }[]; index: number } | null>(
    null,
);
const toggleOpen = computed({
    get: () => toggling.value !== null,
    set: (v) => {
        if (!v) toggling.value = null;
    },
});

function askToggle(rows: { active: boolean }[], index: number) {
    if (rows[index].active) {
        toggling.value = { rows, index };
    } else {
        rows[index].active = true;
        toast.success('Élément réactivé (maquette).');
    }
}

function confirmToggle(reason: string) {
    if (toggling.value) {
        toggling.value.rows[toggling.value.index].active = false;
        toast.success(`Désactivé — motif : ${reason}`);
    }
}

const stateCell = (
    rows: () => { active: boolean }[],
    rowId: number,
    ids: () => number[],
) => {
    const index = ids().indexOf(rowId);
    const active = rows()[index]?.active;

    return h('div', { class: 'flex items-center gap-2' }, [
        toneBadgeCell(
            active ? 'Actif' : 'Désactivé',
            active ? 'success' : 'neutral',
        ),
        canEdit.value
            ? h(
                  Button,
                  {
                      size: 'sm',
                      variant: 'ghost',
                      onClick: () => askToggle(rows(), index),
                  },
                  () => (active ? 'Désactiver' : 'Réactiver'),
              )
            : null,
    ]);
};

const agencyCols: DataTableColumn<Agency>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Agence' },
    { accessorKey: 'city', header: 'Ville' },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) =>
            stateCell(
                () => agencyRows.value,
                row.original.id,
                () => agencyRows.value.map((a) => a.id),
            ),
    },
];
const serviceCols: DataTableColumn<Service>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Service' },
    { accessorKey: 'users', header: 'Utilisateurs' },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) =>
            stateCell(
                () => serviceRows.value,
                row.original.id,
                () => serviceRows.value.map((s) => s.id),
            ),
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
        cell: ({ row }) =>
            stateCell(
                () => siteRows.value,
                row.original.id,
                () => siteRows.value.map((s) => s.id),
            ),
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
