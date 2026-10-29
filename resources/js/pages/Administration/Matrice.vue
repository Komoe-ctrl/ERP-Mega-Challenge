<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import PageHeader from '@/components/erp/PageHeader.vue';

type RoleRow = { name: string; label: string; permissions: string[] };

defineProps<{
    roles: RoleRow[];
    modules: { value: string; label: string }[];
    actions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/administration/users' },
            { title: 'Matrice des droits', href: '/administration/matrice' },
        ],
    },
});
</script>

<template>
    <Head title="Matrice des droits" />

    <div class="space-y-4 p-4">
        <PageHeader
            title="Matrice rôles × permissions"
            description="Vue d’ensemble réelle des droits, par module et par action. Pour modifier un rôle : onglet Rôles."
        />
        <AdminNav />

        <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full min-w-[900px] text-sm">
                <thead>
                    <tr class="bg-muted/50">
                        <th
                            class="sticky left-0 z-10 bg-muted p-3 text-left font-medium"
                        >
                            Module / action
                        </th>
                        <th
                            v-for="r in roles"
                            :key="r.name"
                            class="p-2 text-center align-bottom text-xs font-medium"
                        >
                            <span class="block max-w-20 leading-tight">{{
                                r.label
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="m in modules" :key="m.value">
                        <tr class="border-t bg-muted/30">
                            <th
                                class="sticky left-0 z-10 bg-muted p-2 px-3 text-left font-semibold"
                                :colspan="roles.length + 1"
                            >
                                {{ m.label }}
                            </th>
                        </tr>
                        <tr
                            v-for="a in actions"
                            :key="m.value + a.value"
                            class="border-t"
                        >
                            <td
                                class="sticky left-0 z-10 bg-card p-2 px-3 pl-6 text-muted-foreground"
                            >
                                {{ a.label }}
                            </td>
                            <td
                                v-for="r in roles"
                                :key="r.name"
                                class="p-2 text-center"
                            >
                                <Check
                                    v-if="
                                        r.permissions.includes(
                                            `${m.value}.${a.value}`,
                                        )
                                    "
                                    class="mx-auto size-4 text-success"
                                    :aria-label="`${r.label} : ${m.label}, ${a.label}`"
                                />
                                <span
                                    v-else
                                    class="text-muted-foreground/40"
                                    aria-hidden="true"
                                    >·</span
                                >
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
