<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Sous-navigation d'un module (onglets-liens). Le lien actif suit l'URL. */
const props = defineProps<{ links: { href: string; label: string }[] }>();

const page = usePage();
const current = computed(() => page.url.split('?')[0]);

// Le lien le plus précis (le plus long) qui correspond à l'URL est actif.
const activeHref = computed(
    () =>
        props.links
            .filter(
                (l) =>
                    current.value === l.href ||
                    current.value.startsWith(l.href + '/'),
            )
            .sort((a, b) => b.href.length - a.href.length)[0]?.href,
);
</script>

<template>
    <nav
        class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"
        aria-label="Sous-navigation"
    >
        <ul class="flex min-w-max gap-1 border-b">
            <li v-for="l in links" :key="l.href">
                <Link
                    :href="l.href"
                    class="-mb-px block border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="
                        activeHref === l.href
                            ? 'border-brand text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ l.label }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
