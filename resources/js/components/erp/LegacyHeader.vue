<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLegacySession } from '@/composables/useLegacySession';
import { usePermissions } from '@/composables/usePermissions';
import { exercicesOuverts } from '@/lib/exercices';
import { fichierGroups, href, topMenu } from '@/lib/legacyMenu';

/**
 * Bandeau unique de l'application : poste · service · menu du haut · exercice ·
 * pointage · cloche. Il reste fixe au défilement.
 */
const { session, poste } = useLegacySession();
const { see } = usePermissions();
const page = usePage();

const unread = computed(() => page.props.unreadNotifications);
const recent = computed(() => page.props.recentNotifications);
const path = computed(() => page.url.split('?')[0]);

const fichier = computed(() =>
    fichierGroups
        .map((g) => ({ ...g, items: g.items.filter((i) => see(i.permission)) }))
        .filter((g) => g.items.length > 0),
);
const visibleTop = computed(() => topMenu.filter((m) => see(m.permission)));

const isActive = (target: string): boolean =>
    path.value === target || path.value.startsWith(`${target}/`);
const fichierActive = computed(() =>
    fichier.value.some((g) => g.items.some((i) => isActive(href(i.slug)))),
);

// « Arrivée pointée à 08 h 02 »
const arrival = computed(() => {
    const at = session.value.arrivedAt;

    return at ? `${at.slice(11, 13)} h ${at.slice(14, 16)}` : null;
});

const linkClass = (active: boolean): string =>
    `relative rounded-md px-2.5 py-1.5 text-sm hover:bg-accent after:absolute after:inset-x-2.5 after:-bottom-[7px] after:h-0.5 after:rounded-full ${
        active ? 'font-medium after:bg-brand' : 'after:bg-transparent'
    }`;

function quit() {
    router.post('/logout');
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-1 gap-y-1 border-b bg-card px-3 py-1.5"
        data-test="legacy-header"
    >
        <SidebarTrigger class="mr-1" />
        <div class="mr-3 flex items-center gap-2 text-sm">
            <span class="font-medium" data-test="header-title">{{
                poste.title
            }}</span>
            <span class="text-muted-foreground">·</span>
            <span class="text-muted-foreground" data-test="header-service">{{
                poste.service
            }}</span>
        </div>

        <nav
            class="flex flex-wrap items-center gap-0.5"
            aria-label="Menu principal"
        >
            <DropdownMenu v-if="fichier.length">
                <DropdownMenuTrigger
                    :class="[
                        linkClass(fichierActive),
                        'inline-flex items-center gap-1 outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    ]"
                >
                    Fichier <ChevronDown class="size-3.5" />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    class="w-[min(46rem,calc(100vw-2rem))] p-3"
                    data-test="fichier-panel"
                >
                    <div
                        class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div v-for="g in fichier" :key="g.label">
                            <DropdownMenuLabel
                                class="px-2 pb-1 text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                {{ g.label }}
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="item in g.items"
                                :key="item.slug"
                                as-child
                                :class="
                                    isActive(href(item.slug))
                                        ? 'bg-accent font-medium text-foreground'
                                        : ''
                                "
                            >
                                <Link :href="href(item.slug)" prefetch>{{
                                    item.label
                                }}</Link>
                            </DropdownMenuItem>
                        </div>
                    </div>
                    <DropdownMenuSeparator class="my-2" />
                    <DropdownMenuItem @select="quit">
                        <LogOut class="size-4" /> Quitter
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Link
                v-for="m in visibleTop"
                :key="m.href"
                :href="m.href"
                prefetch
                :class="linkClass(isActive(m.href))"
                :aria-current="isActive(m.href) ? 'page' : undefined"
            >
                {{ m.label }}
            </Link>
        </nav>

        <div class="ml-auto flex items-center gap-3 text-sm">
            <span
                v-if="arrival"
                class="text-muted-foreground"
                data-test="arrival-status"
                >Arrivée pointée à {{ arrival }}</span
            >
            <label class="flex items-center gap-1.5">
                <span class="text-muted-foreground">Exercice</span>
                <select
                    v-model.number="session.exercice"
                    class="rounded-md border bg-background px-2 py-1 tabular-nums"
                    aria-label="Exercice en cours"
                    data-test="exercice-select"
                >
                    <option v-for="y in exercicesOuverts" :key="y" :value="y">
                        {{ y }}
                    </option>
                </select>
            </label>

            <DropdownMenu>
                <DropdownMenuTrigger
                    class="relative rounded-md p-2 outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-label="
                        unread
                            ? `Notifications (${unread} non lues)`
                            : 'Notifications'
                    "
                    data-test="notifications-bell"
                >
                    <Bell class="size-5" />
                    <span
                        v-if="unread"
                        class="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-cta px-1 text-center text-[10px] font-semibold text-cta-foreground"
                        >{{ unread }}</span
                    >
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-96 p-0">
                    <div class="border-b px-3 py-2 text-sm font-medium">
                        Notifications
                        <span class="font-normal text-muted-foreground"
                            >· {{ unread }} non lue{{
                                unread > 1 ? 's' : ''
                            }}</span
                        >
                    </div>
                    <DropdownMenuItem
                        v-for="n in recent"
                        :key="n.id"
                        as-child
                        class="items-start rounded-none px-3 py-2"
                    >
                        <Link :href="n.href">
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ n.title }}</span
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ n.body }}</span
                                >
                            </span>
                        </Link>
                    </DropdownMenuItem>
                    <p
                        v-if="!recent.length"
                        class="px-3 py-4 text-center text-sm text-muted-foreground"
                    >
                        Aucune notification non lue.
                    </p>
                    <DropdownMenuSeparator class="my-0" />
                    <DropdownMenuItem
                        as-child
                        class="justify-center rounded-none py-2 text-brand"
                    >
                        <Link
                            href="/notifications"
                            data-test="notifications-all"
                            >Voir toutes les notifications</Link
                        >
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
