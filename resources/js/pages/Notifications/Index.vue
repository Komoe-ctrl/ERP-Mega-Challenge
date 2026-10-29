<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bell,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Package,
    Wrench,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import EmptyState from '@/components/erp/EmptyState.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { dateTimeFr } from '@/lib/format';
import { toneClasses } from '@/lib/status';
import type { Tone } from '@/lib/status';
import { notifications } from '@/mocks/transverse';
import type { NotificationKind } from '@/types/erp';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifications', href: '/notifications' }],
    },
});

const items = reactive(notifications.map((n) => ({ ...n })));
const tab = ref('toutes');
const shown = computed(() =>
    tab.value === 'non_lues' ? items.filter((n) => !n.read) : items,
);
const unread = computed(() => items.filter((n) => !n.read).length);

const meta: Record<NotificationKind, { icon: typeof Bell; tone: Tone }> = {
    demande: { icon: ClipboardList, tone: 'warning' },
    decision: { icon: CheckCircle2, tone: 'success' },
    budget: { icon: AlertTriangle, tone: 'danger' },
    planning: { icon: CalendarClock, tone: 'brand' },
    stock: { icon: Package, tone: 'warning' },
    engin: { icon: Wrench, tone: 'info' },
    echeance: { icon: CalendarClock, tone: 'info' },
};

const channels = reactive({ app: true, email: true });
</script>

<template>
    <Head title="Notifications" />

    <div class="mx-auto w-full max-w-3xl space-y-4 p-4">
        <PageHeader
            title="Notifications"
            description="Une même alerte n’est pas renvoyée plus d’une fois par jour."
        >
            <Button
                variant="outline"
                :disabled="!unread"
                @click="items.forEach((n) => (n.read = true))"
            >
                Tout marquer comme lu
            </Button>
        </PageHeader>

        <TabsNav
            v-model="tab"
            :tabs="[
                { key: 'toutes', label: 'Toutes', count: items.length },
                { key: 'non_lues', label: 'Non lues', count: unread },
            ]"
        />

        <ul v-if="shown.length" class="space-y-2">
            <li v-for="n in shown" :key="n.id">
                <Link
                    :href="n.href"
                    class="flex gap-3 rounded-xl border bg-card p-3 shadow-xs hover:bg-accent"
                    :class="n.read ? 'opacity-80' : 'border-l-4 border-l-brand'"
                    @click="n.read = true"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                        :class="toneClasses[meta[n.kind].tone]"
                    >
                        <component :is="meta[n.kind].icon" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block text-sm"
                            :class="n.read ? '' : 'font-semibold'"
                            >{{ n.title }}</span
                        >
                        <span class="block text-sm text-muted-foreground">{{
                            n.body
                        }}</span>
                        <span
                            class="mt-0.5 block text-xs text-muted-foreground"
                            >{{ dateTimeFr(n.date.replace(' ', 'T')) }}</span
                        >
                    </span>
                </Link>
            </li>
        </ul>
        <EmptyState
            v-else
            title="Aucune notification"
            description="Vous êtes à jour."
        />

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <h2 class="mb-2 font-display font-semibold">Canaux de réception</h2>
            <label class="flex items-center gap-2 py-1 text-sm">
                <input v-model="channels.app" type="checkbox" class="size-4" />
                Centre de notifications de l’application
            </label>
            <label class="flex items-center gap-2 py-1 text-sm">
                <input
                    v-model="channels.email"
                    type="checkbox"
                    class="size-4"
                />
                E-mail
            </label>
        </section>
    </div>
</template>
