<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { toneBadgeCell } from '@/lib/cells';
import type { DataTableColumn } from '@/lib/dataTable';
import { agencies, services, sites } from '@/mocks/transverse';
import type { Agency, Service, SiteRecord } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Organisation', href: '/administration/organisation' },
        ],
    },
});

const tab = ref('agences');

const active = (v: boolean) =>
    toneBadgeCell(v ? 'Actif' : 'Désactivé', v ? 'success' : 'neutral');
const siteTypes = {
    bureau: 'Bureau',
    depot_central: 'Dépôt central',
    magasin_chantier: 'Magasin de chantier',
} as const;

const agencyCols: DataTableColumn<Agency>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Agence' },
    { accessorKey: 'city', header: 'Ville' },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) => active(row.original.active),
    },
];
const serviceCols: DataTableColumn<Service>[] = [
    { accessorKey: 'code', header: 'Code' },
    { accessorKey: 'name', header: 'Service' },
    { accessorKey: 'users', header: 'Utilisateurs' },
    {
        accessorKey: 'active',
        header: 'État',
        cell: ({ row }) => active(row.original.active),
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
        cell: ({ row }) => active(row.original.active),
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
            <Button @click="toast.success('Création (maquette).')"
                ><Plus /> Ajouter</Button
            >
        </PageHeader>
        <AdminNav />
        <TabsNav
            v-model="tab"
            :tabs="[
                { key: 'agences', label: 'Agences', count: agencies.length },
                { key: 'services', label: 'Services', count: services.length },
                { key: 'sites', label: 'Sites', count: sites.length },
            ]"
        />
        <DataTable
            v-if="tab === 'agences'"
            :columns="agencyCols"
            :data="agencies"
            :searchable="false"
            :paginate="false"
        />
        <DataTable
            v-else-if="tab === 'services'"
            :columns="serviceCols"
            :data="services"
            :paginate="false"
        />
        <DataTable v-else :columns="siteCols" :data="sites" :paginate="false" />
    </div>
</template>
