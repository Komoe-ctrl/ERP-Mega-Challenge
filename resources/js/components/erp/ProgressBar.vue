<script setup lang="ts">
import { computed } from 'vue';
import { toneBar } from '@/lib/status';
import type { Tone } from '@/lib/status';

const props = withDefaults(
    defineProps<{
        value: number;
        tone?: Tone;
        label?: string;
        showValue?: boolean;
        /** Repère (ex. avancement théorique) affiché sur la barre. */
        marker?: number;
    }>(),
    { tone: 'info', showValue: true },
);

const clamped = computed(() => Math.min(100, Math.max(0, props.value)));
</script>

<template>
    <div class="w-full">
        <div
            v-if="label || showValue"
            class="mb-1 flex items-center justify-between text-xs"
        >
            <span class="text-muted-foreground">{{ label }}</span>
            <span v-if="showValue" class="font-medium tabular-nums">
                {{ Math.round(value) }} %
            </span>
        </div>
        <div
            class="relative h-2 w-full overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="Math.round(clamped)"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full transition-all"
                :class="toneBar[tone]"
                :style="{ width: `${clamped}%` }"
            />
            <div
                v-if="marker !== undefined"
                class="absolute top-0 h-full w-0.5 bg-foreground/60"
                :style="{ left: `${Math.min(100, Math.max(0, marker))}%` }"
                title="Avancement théorique"
            />
        </div>
    </div>
</template>
