<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{ page: Omit<Paginated<unknown>, 'data'> }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-2 text-sm text-muted-foreground sm:flex-row"
    >
        <p>
            <template v-if="page.total > 0">
                {{ page.from }}–{{ page.to }} sur {{ page.total }}
            </template>
            <template v-else>0 résultat</template>
        </p>
        <div class="flex items-center gap-2">
            <Button
                v-if="page.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="page.prev_page_url" preserve-scroll
                    >Précédent</Link
                >
            </Button>
            <span v-if="page.last_page > 1">
                Page {{ page.current_page }} / {{ page.last_page }}
            </span>
            <Button
                v-if="page.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="page.next_page_url" preserve-scroll>Suivant</Link>
            </Button>
        </div>
    </div>
</template>
