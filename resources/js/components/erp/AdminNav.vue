<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const links = [
    { href: '/administration/users', label: 'Utilisateurs' },
    { href: '/administration/roles', label: 'Rôles' },
    { href: '/administration/matrice', label: 'Matrice des droits' },
    { href: '/administration/organisation', label: 'Agences, services, sites' },
    { href: '/administration/parametres', label: 'Paramètres' },
    { href: '/administration/journal', label: 'Journal des activités' },
];

const current = computed(() => page.url.split('?')[0]);
</script>

<template>
    <nav
        class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"
        aria-label="Administration"
    >
        <ul class="flex min-w-max gap-1 border-b">
            <li v-for="l in links" :key="l.href">
                <Link
                    :href="l.href"
                    class="-mb-px block border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="
                        current.startsWith(l.href)
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
