<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { href, rubriquesMenu } from '@/lib/legacyMenu';

const { can } = usePermissions();
const { isCurrentUrl } = useCurrentUrl();

// Une seule rubrique ouverte à la fois, comme dans l'application actuelle.
const groups = computed(() =>
    rubriquesMenu
        .map((g) => ({ ...g, items: g.items.filter((i) => can(i.permission)) }))
        .filter((g) => g.items.length > 0),
);
const opened = ref<string | null>(null);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Rubriques</SidebarGroupLabel>
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
                        <SidebarMenuButton :tooltip="g.label">
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
                                :key="item.slug"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(href(item.slug))"
                                >
                                    <Link :href="href(item.slug)">
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
