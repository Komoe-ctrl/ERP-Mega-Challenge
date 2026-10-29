<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        compact?: boolean;
    }>(),
    { compact: false },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 text-center text-muted-foreground"
        :class="
            compact ? 'py-8' : 'rounded-xl border border-dashed bg-card py-12'
        "
    >
        <component :is="icon ?? Inbox" class="size-8 opacity-50" />
        <p class="text-sm font-medium text-foreground">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
