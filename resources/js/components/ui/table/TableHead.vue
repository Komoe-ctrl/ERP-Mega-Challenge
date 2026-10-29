<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <th data-slot="th" :class="cn('h-10 px-3 text-left align-middle font-medium whitespace-nowrap text-muted-foreground', props.class)">
        <slot />
    </th>
</template>
