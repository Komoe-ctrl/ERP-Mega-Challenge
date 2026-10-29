<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import RoleSwitcher from '@/components/erp/RoleSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { moduleIcon } from '@/lib/moduleIcons';
import { notifications } from '@/mocks/transverse';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const unread = notifications.filter((n) => !n.read).length;

// Le menu est construit côté serveur selon les droits de l'utilisateur
// (module activé + permission `<module>.view`) : un module sans droit n'apparaît pas.
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Tableau de bord',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Notifications',
        href: '/notifications',
        icon: Bell,
        badge: unread,
    },
    ...page.props.navigation.map((module) => ({
        title: module.title,
        href: module.href,
        icon: moduleIcon(module.icon),
    })),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <RoleSwitcher />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
