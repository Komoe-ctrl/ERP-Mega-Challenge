<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { toast } from 'vue-sonner';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import EmptyState from '@/components/erp/EmptyState.vue';
import PageHeader from '@/components/erp/PageHeader.vue';
import TabsNav from '@/components/erp/TabsNav.vue';
import { Button } from '@/components/ui/button';
import { dateTimeFr } from '@/lib/format';
import { toneClasses } from '@/lib/status';
import type { Tone } from '@/lib/status';
import type { NotificationKind } from '@/types/erp';

type Item = {
    id: string;
    kind: NotificationKind;
    title: string;
    body: string;
    date: string;
    read: boolean;
    href: string;
};

const props = defineProps<{
    notifications: Item[];
    channels: { app: boolean; email: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifications', href: '/notifications' }],
    },
});

const tab = ref('toutes');
const shown = computed(() =>
    tab.value === 'non_lues'
        ? props.notifications.filter((n) => !n.read)
        : props.notifications,
);
const unread = computed(
    () => props.notifications.filter((n) => !n.read).length,
);

const meta: Record<NotificationKind, { icon: typeof Bell; tone: Tone }> = {
    demande: { icon: ClipboardList, tone: 'warning' },
    decision: { icon: CheckCircle2, tone: 'success' },
    budget: { icon: AlertTriangle, tone: 'danger' },
    planning: { icon: CalendarClock, tone: 'brand' },
    stock: { icon: Package, tone: 'warning' },
    engin: { icon: Wrench, tone: 'info' },
    echeance: { icon: CalendarClock, tone: 'info' },
};

// Ouvrir une notification la marque comme lue, puis mène à l'objet concerné.
function open(n: Item) {
    router.post(
        NotificationController.read.url(n.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => router.visit(n.href),
        },
    );
}

function readAll() {
    router.post(
        NotificationController.readAll.url(),
        {},
        { preserveScroll: true },
    );
}

// Canaux de réception paramétrables par utilisateur : application et e-mail.
const channels = reactive({ ...props.channels });

function saveChannels() {
    router.put(
        NotificationController.channels.url(),
        { ...channels },
        {
            preserveScroll: true,
            onError: (errors) => toast.error(Object.values(errors)[0]),
        },
    );
}
</script>

<template>
    <Head title="Notifications" />

    <div class="mx-auto w-full max-w-3xl space-y-4 p-4">
        <PageHeader
            title="Notifications"
            description="Une même alerte n’est pas renvoyée plus d’une fois par jour."
        >
            <Button variant="outline" :disabled="!unread" @click="readAll">
                Tout marquer comme lu
            </Button>
        </PageHeader>

        <TabsNav
            v-model="tab"
            :tabs="[
                { key: 'toutes', label: 'Toutes', count: notifications.length },
                { key: 'non_lues', label: 'Non lues', count: unread },
            ]"
        />

        <ul v-if="shown.length" class="space-y-2">
            <li v-for="n in shown" :key="n.id">
                <button
                    type="button"
                    class="flex w-full gap-3 rounded-xl border bg-card p-3 text-left shadow-xs hover:bg-accent"
                    :class="n.read ? 'opacity-80' : 'border-l-4 border-l-brand'"
                    @click="open(n)"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                        :class="toneClasses[meta[n.kind]?.tone ?? 'neutral']"
                    >
                        <component
                            :is="meta[n.kind]?.icon ?? Bell"
                            class="size-5"
                        />
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
                </button>
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
                <input
                    v-model="channels.app"
                    type="checkbox"
                    class="size-4"
                    @change="saveChannels"
                />
                Centre de notifications de l’application
            </label>
            <label class="flex items-center gap-2 py-1 text-sm">
                <input
                    v-model="channels.email"
                    type="checkbox"
                    class="size-4"
                    @change="saveChannels"
                />
                E-mail
            </label>
        </section>
    </div>
</template>
