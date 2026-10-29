<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { moduleIcon } from '@/lib/moduleIcons';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tableau de bord',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const modules = computed(() => page.props.navigation);
</script>

<template>
    <Head title="Tableau de bord" />

    <div class="space-y-6 p-4">
        <Heading
            :title="`Bonjour, ${user.name}`"
            description="Accédez aux modules de l’ERP auxquels votre profil donne droit."
        />

        <p v-if="modules.length === 0" class="text-sm text-muted-foreground">
            Aucun module n’est encore disponible pour votre profil.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="module in modules"
                :key="module.key"
                :href="module.href"
                class="flex items-center gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/50"
            >
                <component :is="moduleIcon(module.icon)" class="size-6" />
                <span class="font-medium">{{ module.title }}</span>
            </Link>
        </div>
    </div>
</template>
