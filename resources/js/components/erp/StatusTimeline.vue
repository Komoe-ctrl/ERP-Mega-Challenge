<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Frise des statuts (ex. cycle d'un projet : Étude → … → Clôturé).
 * Les étapes passées sont cochées, l'étape courante est mise en avant.
 */
const props = defineProps<{
    steps: { key: string; label: string }[];
    current: string;
    /** Statut hors parcours (ex. « Abandonné ») : la frise est grisée. */
    aborted?: boolean;
}>();

const currentIndex = computed(() =>
    props.steps.findIndex((s) => s.key === props.current),
);
</script>

<template>
    <ol
        class="flex gap-0 overflow-x-auto pb-1"
        :class="aborted ? 'opacity-50' : ''"
        aria-label="Frise des statuts"
    >
        <li
            v-for="(step, index) in steps"
            :key="step.key"
            class="relative flex min-w-24 flex-1 flex-col items-center gap-1.5 px-1 text-center"
        >
            <span
                v-if="index > 0"
                class="absolute top-3.5 right-1/2 h-0.5 w-full"
                :class="index <= currentIndex ? 'bg-brand' : 'bg-border'"
                aria-hidden="true"
            />
            <span
                class="relative z-10 flex size-7 items-center justify-center rounded-full border-2 text-xs font-semibold"
                :class="
                    index < currentIndex
                        ? 'border-brand bg-brand text-brand-foreground'
                        : index === currentIndex
                          ? 'border-brand bg-card text-brand-strong'
                          : 'border-border bg-card text-muted-foreground'
                "
            >
                <Check v-if="index < currentIndex" class="size-4" />
                <template v-else>{{ index + 1 }}</template>
            </span>
            <span
                class="text-xs leading-tight"
                :class="
                    index === currentIndex
                        ? 'font-semibold text-foreground'
                        : 'text-muted-foreground'
                "
            >
                {{ step.label }}
            </span>
        </li>
    </ol>
</template>
