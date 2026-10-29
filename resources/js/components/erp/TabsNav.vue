<script setup lang="ts">
defineProps<{ tabs: { key: string; label: string; count?: number }[] }>();

const active = defineModel<string>({ required: true });
</script>

<template>
    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-1 border-b" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="active === tab.key"
                class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    active === tab.key
                        ? 'border-brand text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="active = tab.key"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count !== undefined"
                    class="rounded-full bg-muted px-1.5 text-xs tabular-nums"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>
    </div>
</template>
