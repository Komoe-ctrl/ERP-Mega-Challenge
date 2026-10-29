<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { toneClasses } from '@/lib/status';
import type { Tone } from '@/lib/status';

defineProps<{
    label: string;
    value: string;
    hint?: string;
    tone?: Tone;
    icon?: Component;
    href?: string;
}>();
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex items-start justify-between gap-3 rounded-xl border bg-card p-4 shadow-xs"
        :class="href ? 'transition-colors hover:bg-accent' : ''"
    >
        <div class="min-w-0">
            <p class="text-xs font-medium text-muted-foreground">{{ label }}</p>
            <p class="mt-1 truncate font-display text-2xl font-semibold">
                {{ value }}
            </p>
            <p v-if="hint" class="mt-0.5 text-xs text-muted-foreground">
                {{ hint }}
            </p>
        </div>
        <span
            v-if="icon"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg"
            :class="toneClasses[tone ?? 'neutral']"
        >
            <component :is="icon" class="size-5" />
        </span>
    </component>
</template>
