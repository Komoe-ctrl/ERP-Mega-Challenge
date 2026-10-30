<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import { useLegacySession } from '@/composables/useLegacySession';
import { fichierMenu, href, topMenu } from '@/lib/legacyMenu';

const { session, poste } = useLegacySession();
const { can } = usePermissions();
const page = usePage();
const unread = computed(() => page.props.unreadNotifications);

const visibleFichier = computed(() =>
    fichierMenu.filter((item, index, all) =>
        item === 'separator'
            ? index > 0 && all[index - 1] !== 'separator'
            : !item.permission || can(item.permission),
    ),
);
const visibleTop = computed(() =>
    topMenu.filter((m) => !m.permission || can(m.permission)),
);

function quit() {
    router.post('/logout');
}
</script>

<template>
    <div class="border-b bg-card">
        <div
            class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm"
            data-test="legacy-header"
        >
            <p class="font-medium" data-test="header-title">
                {{ poste.title }}
            </p>
            <span class="text-muted-foreground">·</span>
            <p class="text-muted-foreground" data-test="header-service">
                {{ poste.service }}
            </p>
            <span class="text-muted-foreground">·</span>
            <label class="flex items-center gap-1.5">
                <span class="text-muted-foreground">Exercice en cours</span>
                <input
                    v-model.number="session.exercice"
                    type="number"
                    min="2000"
                    max="2100"
                    class="w-20 rounded-md border bg-background px-2 py-0.5 tabular-nums"
                    aria-label="Exercice en cours"
                />
            </label>
        </div>
        <nav
            class="flex flex-wrap items-center gap-1 border-t px-3 py-1 text-sm"
            aria-label="Menu principal"
        >
            <DropdownMenu>
                <DropdownMenuTrigger
                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 font-medium hover:bg-accent"
                >
                    Fichier <ChevronDown class="size-3.5" />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    class="max-h-[70vh] w-80 overflow-y-auto"
                >
                    <template v-for="(item, i) in visibleFichier" :key="i">
                        <DropdownMenuSeparator v-if="item === 'separator'" />
                        <DropdownMenuItem v-else as-child>
                            <Link :href="href(item.slug)">{{
                                item.label
                            }}</Link>
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @select="quit">
                        <LogOut class="size-4" /> Quitter
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Link
                v-for="m in visibleTop"
                :key="m.href"
                :href="m.href"
                class="rounded-md px-2.5 py-1.5 hover:bg-accent"
            >
                {{ m.label }}
            </Link>
            <Link
                href="/notifications"
                class="relative ml-auto rounded-md p-2 hover:bg-accent"
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
            </Link>
        </nav>
    </div>
</template>
