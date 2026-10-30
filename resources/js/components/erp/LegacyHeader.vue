<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
import { services } from '@/mocks/legacy';

const { session } = useLegacySession();
const { can } = usePermissions();

const service = computed(
    () => services.find((s) => s.id === session.value.serviceId) ?? services[0],
);
const visibleFichier = computed(() =>
    fichierMenu.filter((item, index, all) =>
        item === 'separator'
            ? index > 0 && all[index - 1] !== 'separator'
            : can(item.permission),
    ),
);
const visibleTop = computed(() => topMenu.filter((m) => can(m.permission)));

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
            <AppLogoIcon class="size-6 shrink-0 fill-current text-brand" />
            <p class="font-medium">{{ session.title }}</p>
            <span class="text-muted-foreground">·</span>
            <p class="text-muted-foreground">{{ service.shortName }}</p>
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
            <Link
                href="/ecrans/identification"
                class="ml-auto text-xs text-muted-foreground underline-offset-2 hover:underline"
            >
                Changer de poste
            </Link>
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
        </nav>
    </div>
</template>
