<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import LegacyHeader from '@/components/erp/LegacyHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <div
                class="sticky top-0 z-30 bg-background"
                data-test="sticky-header"
            >
                <LegacyHeader />
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            </div>
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
