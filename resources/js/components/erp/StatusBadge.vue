<script setup lang="ts">
import { computed } from 'vue';
import { statusMeta, toneClasses } from '@/lib/status';
import type { StatusKind, Tone } from '@/lib/status';

const props = defineProps<{
    kind?: StatusKind;
    status?: string;
    label?: string;
    tone?: Tone;
}>();

const meta = computed(() => {
    if (props.kind && props.status) {
        return statusMeta(props.kind, props.status);
    }

    return {
        label: props.label ?? props.status ?? '',
        tone: props.tone ?? 'neutral',
    };
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="toneClasses[props.tone ?? meta.tone]"
    >
        {{ props.label ?? meta.label }}
    </span>
</template>
