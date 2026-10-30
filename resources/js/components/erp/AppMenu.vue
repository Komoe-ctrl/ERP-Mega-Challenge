<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { sidebarMenu } from '@/lib/legacyMenu';

const page = usePage();
const { can } = usePermissions();

const path = computed(() => page.url.split('?')[0]);

// Menu unique, filtré par rôle ; les groupes sans écran autorisé disparaissent.
const groups = computed(() =>
    sidebarMenu
        .map((g) => ({
            ...g,
            items: g.items.filter((i) => !i.permission || can(i.permission)),
        }))
        .filter((g) => g.items.length > 0),
);

// L'entrée la plus précise qui correspond à l'URL est active.
const activeHref = computed(
    () =>
        groups.value
            .flatMap((g) => g.items)
            .filter(
                (i) =>
                    path.value === i.href ||
                    path.value.startsWith(i.href + '/'),
            )
            .sort((a, b) => b.href.length - a.href.length)[0]?.href,
);
const groupOfActive = computed(
    () =>
        groups.value.find((g) =>
            g.items.some((i) => i.href === activeHref.value),
        )?.key ?? null,
);

// Une seule catégorie ouverte à la fois ; celle de la page courante s'ouvre seule.
const opened = ref<string | null>(groupOfActive.value);
watch(groupOfActive, (key) => {
    if (key) {
        opened.value = key;
    }
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <Collapsible
                v-for="g in groups"
                :key="g.key"
                as-child
                :open="opened === g.key"
                @update:open="(v: boolean) => (opened = v ? g.key : null)"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton>
                            <span>{{ g.label }}</span>
                            <ChevronRight
                                class="ml-auto transition-transform"
                                :class="opened === g.key ? 'rotate-90' : ''"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="item in g.items"
                                :key="item.href"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="item.href === activeHref"
                                >
                                    <Link :href="item.href">
                                        <span>{{ item.label }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
