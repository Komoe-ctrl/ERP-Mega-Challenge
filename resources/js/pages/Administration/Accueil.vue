<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UserCheck, UserX, Users } from '@lucide/vue';
import AdminNav from '@/components/erp/AdminNav.vue';
import KpiCard from '@/components/erp/KpiCard.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import StatusBadge from '@/components/erp/StatusBadge.vue';
import { dateTimeFr } from '@/lib/format';
import type { ActivityEntry } from '@/types/erp';

defineProps<{
    stats: { users: number; active: number; inactive: number };
    recentUsers: {
        id: number;
        name: string;
        email: string;
        job_title: string | null;
        is_active: boolean;
    }[];
    entries: ActivityEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Administration', href: '/administration' }],
    },
});
</script>

<template>
    <Head title="Accueil Administration" />

    <div class="space-y-5 p-4">
        <PageHeader
            title="Accueil Administration"
            description="Comptes, droits et dernières actions enregistrées dans le journal."
        />
        <AdminNav />

        <div class="grid gap-3 sm:grid-cols-3">
            <KpiCard
                label="Comptes"
                :value="String(stats.users)"
                :icon="Users"
                href="/administration/users"
            />
            <KpiCard
                label="Comptes actifs"
                :value="String(stats.active)"
                :icon="UserCheck"
                tone="success"
            />
            <KpiCard
                label="Comptes désactivés"
                :value="String(stats.inactive)"
                :icon="UserX"
                tone="neutral"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card shadow-xs">
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="font-display text-base font-semibold">
                        Derniers utilisateurs
                    </h2>
                    <Link
                        href="/administration/users"
                        class="text-sm text-brand hover:underline"
                        >Tous les utilisateurs</Link
                    >
                </div>
                <ul class="divide-y">
                    <li
                        v-for="u in recentUsers"
                        :key="u.id"
                        class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ u.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ u.job_title ?? u.email }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="u.is_active ? 'Actif' : 'Désactivé'"
                            :tone="u.is_active ? 'success' : 'neutral'"
                        />
                    </li>
                    <li
                        v-if="!recentUsers.length"
                        class="px-4 py-6 text-center text-sm text-muted-foreground"
                    >
                        Aucun utilisateur.
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border bg-card shadow-xs">
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="font-display text-base font-semibold">
                        Dernières entrées du journal
                    </h2>
                    <Link
                        href="/administration/journal"
                        class="text-sm text-brand hover:underline"
                        >Journal complet</Link
                    >
                </div>
                <ul class="divide-y">
                    <li
                        v-for="e in entries"
                        :key="e.id"
                        class="flex items-start justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate">
                                <span class="font-medium">{{ e.actor }}</span>
                                · {{ e.subject }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ dateTimeFr(e.date) }}
                            </p>
                        </div>
                        <StatusBadge kind="activity" :status="e.event" />
                    </li>
                    <li
                        v-if="!entries.length"
                        class="px-4 py-6 text-center text-sm text-muted-foreground"
                    >
                        Aucune entrée dans le journal.
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
