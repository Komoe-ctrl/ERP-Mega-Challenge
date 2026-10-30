<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

// Logo Mega Challenge : version couleur en thème clair, version blanche en thème sombre.
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <span class="inline-flex" :class="className">
        <img
            src="/brand/logo-mega-challenge.svg"
            alt="Mega Challenge"
            class="h-full w-auto dark:hidden"
        />
        <img
            src="/brand/logo-mega-challenge-blanc.svg"
            alt="Mega Challenge"
            class="hidden h-full w-auto dark:block"
        />
    </span>
</template>
