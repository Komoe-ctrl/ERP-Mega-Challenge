<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { h, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Administration/UserController';
import DataTable from '@/components/DataTable.vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import Heading from '@/components/Heading.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { DataTableColumn } from '@/lib/dataTable';
import type { Paginated } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    agency: string | null;
    service: string | null;
    roles: string[];
};

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: { q: string };
    can: { create: boolean; update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Utilisateurs', href: '/administration/users' },
        ],
    },
});

const search = ref(props.filters.q);

function submitSearch() {
    router.get(
        UserController.index.url(),
        { q: search.value || undefined },
        { preserveState: true, replace: true },
    );
}

function toggleActive(user: UserRow) {
    const action = user.is_active
        ? UserController.deactivate
        : UserController.reactivate;

    router.patch(action.url(user.id), {}, { preserveScroll: true });
}

const columns: DataTableColumn<UserRow>[] = [
    {
        accessorKey: 'name',
        header: 'Nom',
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-medium' }, row.original.name),
                h(
                    'div',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.email,
                ),
            ]),
    },
    {
        id: 'roles',
        header: 'Rôles',
        cell: ({ row }) =>
            h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                row.original.roles.map((role) =>
                    h(Badge, { variant: 'secondary' }, () => role),
                ),
            ),
    },
    {
        id: 'organisation',
        header: 'Agence / service',
        cell: ({ row }) =>
            [row.original.agency, row.original.service]
                .filter(Boolean)
                .join(' · ') || '—',
    },
    {
        id: 'status',
        header: 'Statut',
        cell: ({ row }) =>
            h(
                Badge,
                { variant: row.original.is_active ? 'default' : 'outline' },
                () => (row.original.is_active ? 'Actif' : 'Désactivé'),
            ),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) =>
            props.can.update
                ? h('div', { class: 'flex justify-end gap-2' }, [
                      h(
                          Button,
                          { variant: 'outline', size: 'sm', asChild: true },
                          () =>
                              h(
                                  Link,
                                  {
                                      href: UserController.edit.url(
                                          row.original.id,
                                      ),
                                  },
                                  () => 'Modifier',
                              ),
                      ),
                      h(
                          Button,
                          {
                              variant: 'ghost',
                              size: 'sm',
                              onClick: () => toggleActive(row.original),
                          },
                          () =>
                              row.original.is_active
                                  ? 'Désactiver'
                                  : 'Réactiver',
                      ),
                  ])
                : null,
    },
];
</script>

<template>
    <Head title="Utilisateurs" />

    <div class="space-y-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Utilisateurs"
                description="Comptes, rôles et affectations. Un compte est désactivé, jamais supprimé."
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link href="/administration/roles">Rôles et droits</Link>
                </Button>
                <Button v-if="can.create" as-child>
                    <Link :href="UserController.create.url()">
                        Nouvel utilisateur
                    </Link>
                </Button>
            </div>
        </div>

        <AdminNav />

        <form class="flex gap-2" @submit.prevent="submitSearch">
            <Input
                v-model="search"
                type="search"
                placeholder="Rechercher par nom ou e-mail"
                class="max-w-sm"
            />
            <Button type="submit" variant="secondary">Rechercher</Button>
        </form>

        <DataTable
            :columns="columns"
            :data="users.data"
            :searchable="false"
            :paginate="false"
        />
        <PaginationBar :page="users" />
    </div>
</template>
