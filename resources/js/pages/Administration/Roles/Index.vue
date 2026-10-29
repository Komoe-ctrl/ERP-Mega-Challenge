<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { h } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Administration/RoleController';
import DataTable from '@/components/DataTable.vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { DataTableColumn } from '@/lib/dataTable';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    is_profile: boolean;
    users_count: number;
    permissions_count: number;
};

const props = defineProps<{
    roles: RoleRow[];
    can: { create: boolean; update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Rôles', href: '/administration/roles' },
        ],
    },
});

const columns: DataTableColumn<RoleRow>[] = [
    {
        accessorKey: 'label',
        header: 'Rôle',
        cell: ({ row }) =>
            h('div', { class: 'flex items-center gap-2' }, [
                h('span', { class: 'font-medium' }, row.original.label),
                row.original.is_profile
                    ? h(Badge, { variant: 'outline' }, () => 'Profil')
                    : null,
            ]),
    },
    { accessorKey: 'users_count', header: 'Utilisateurs' },
    { accessorKey: 'permissions_count', header: 'Permissions' },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) =>
            props.can.update
                ? h('div', { class: 'flex justify-end' }, [
                      h(
                          Button,
                          { variant: 'outline', size: 'sm', asChild: true },
                          () =>
                              h(
                                  Link,
                                  {
                                      href: RoleController.edit.url(
                                          row.original.id,
                                      ),
                                  },
                                  () => 'Droits',
                              ),
                      ),
                  ])
                : null,
    },
];
</script>

<template>
    <Head title="Rôles et droits" />

    <div class="space-y-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Rôles et droits"
                description="Les 10 profils de l’ERP et leurs permissions par module."
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link href="/administration/users">Utilisateurs</Link>
                </Button>
                <Button v-if="can.create" as-child>
                    <Link :href="RoleController.create.url()"
                        >Nouveau rôle</Link
                    >
                </Button>
            </div>
        </div>

        <AdminNav />

        <DataTable
            :columns="columns"
            :data="roles"
            :searchable="false"
            :paginate="false"
        />
    </div>
</template>
