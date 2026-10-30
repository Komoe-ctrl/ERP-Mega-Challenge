<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import type { BreadcrumbItem } from '@/types';

// Fil d'Ariane en haut de la page (pas de bandeau séparé) : le bouton du menu
// et les menus sont dans le bandeau unique de l'en-tête.
withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <div v-if="breadcrumbs && breadcrumbs.length > 0" class="px-4 pt-3">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
    </div>
</template>
